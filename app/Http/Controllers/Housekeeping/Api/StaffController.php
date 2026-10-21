<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\HotelResult;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\RankDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The staff roster: everyone at the "counts as staff" rank and up, what they
 * did this week, how they handle support, and the three things changed here -
 * rank (Live: written, then the emulator re-reads it), team and whether they
 * are in the support rotation (Saved: the rotation is read from its table).
 */
class StaffController extends Controller
{
    public function __construct(private readonly RankDirectory $ranks) {}

    public function index(): JsonResponse
    {
        $staffRank = (int) setting('min_staff_rank', 4);
        $week = time() - 7 * 86400;
        $staff = User::query()->where('rank', '>=', $staffRank)->orderByDesc('rank')->orderBy('username')->get(['id', 'username', 'look', 'rank', 'online', 'last_online', 'team_id']);
        $ids = $staff->pluck('id');
        $teams = RoleplaySchema::has('website_teams') ? DB::table('website_teams')->pluck('rank_name', 'id') : collect();
        $actions = DB::table('activity_log')->where('log_name', Audit::LOG)->whereIn('causer_id', $ids)->where('created_at', '>=', date('Y-m-d H:i:s', $week))->groupBy('causer_id')->selectRaw('causer_id, COUNT(*) AS n')->pluck('n', 'causer_id');
        $tool = RoleplaySchema::has('logs_client_staff') ? DB::table('logs_client_staff')->whereIn('user_id', $ids)->where('timestamp', '>=', $week)->groupBy('user_id')->selectRaw('user_id, COUNT(*) AS n')->pluck('n', 'user_id') : collect();
        $threads = RoleplaySchema::has('rp_support_threads') ? DB::table('rp_support_threads')->whereIn('staff_id', $ids)->where('status', 'resolved')->groupBy('staff_id')->selectRaw('staff_id, COUNT(*) AS n')->pluck('n', 'staff_id') : collect();
        $rotation = RoleplaySchema::has('rp_support_rotation') ? DB::table('rp_support_rotation')->whereIn('user_id', $ids)->get()->keyBy('user_id') : collect();

        return response()->json([
            'staff' => $staff->map(fn (User $u): array => [
                'player' => ['id' => (int) $u->id, 'username' => (string) $u->username, 'look' => (string) $u->look],
                'rank' => (int) $u->rank,
                'rank_name' => $this->ranks->name((int) $u->rank),
                'team' => $u->team_id !== null ? ['id' => (int) $u->team_id, 'name' => (string) ($teams->get($u->team_id) ?? '')] : null,
                'online' => (string) $u->online === '1',
                'last_online' => (int) $u->last_online,
                'actions_week' => (int) ($actions->get($u->id) ?? 0) + (int) ($tool->get($u->id) ?? 0),
                'threads' => (int) ($threads->get($u->id) ?? 0),
                'missed' => (int) ($rotation->get($u->id)->missed ?? 0),
                'rotation' => (bool) ($rotation->get($u->id)->available ?? false),
            ])->values(),
            'ranks' => $this->ranks->all()->values(),
            'teams' => $teams->map(fn (mixed $name, mixed $id): array => ['id' => (int) $id, 'name' => (string) $name])->values(),
            'min_staff_rank' => $staffRank,
        ]);
    }

    /**
     * One staff member's week, for the side panel.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $week = time() - 7 * 86400;
        $since = DB::table('activity_log')->where('log_name', Audit::LOG)->where('causer_id', $id)->min('created_at');
        $coins = (int) DB::table('activity_log')->where('log_name', Audit::LOG)->where('causer_id', $id)->where('event', 'coins')->where('created_at', '>=', date('Y-m-d H:i:s', $week))
            ->selectRaw("SUM(CAST(JSON_EXTRACT(properties, '$.after.coins') AS SIGNED) - CAST(JSON_EXTRACT(properties, '$.before.coins') AS SIGNED)) AS total")->value('total');
        $reply = null;

        if (RoleplaySchema::has('rp_support_messages')) {
            $reply = DB::table('rp_support_threads as t')
                ->join('rp_support_messages as m', fn ($j) => $j->on('m.thread_id', '=', 't.id')->where('m.from_staff', 1)->where('m.author_id', $id))
                ->where('t.created_at', '>=', time() - 30 * 86400)
                ->groupBy('t.id', 't.created_at')
                ->selectRaw('MIN(m.created_at) - t.created_at AS wait')
                ->pluck('wait')
                ->avg();
        }

        return response()->json([
            'id' => $id,
            'since' => Epoch::of($since) ?: null,
            'last_online' => (string) $user->online === '1' ? time() : (int) $user->last_online,
            'bans_week' => DB::table('activity_log')->where('log_name', Audit::LOG)->where('causer_id', $id)->where('event', 'ban')->where('created_at', '>=', date('Y-m-d H:i:s', $week))->count()
                + (PlayerColumns::isPlus() ? DB::table('bans')->where('added_by', $user->username)->where('added_date', '>=', (string) $week)->count() : 0),
            'coins_week' => $coins,
            'average_reply' => $reply !== null ? (int) round((float) $reply) : null,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rank' => ['nullable', 'integer', 'min:1', 'max:255'],
            'team_id' => ['nullable', 'integer', 'min:0'],
            'rotation' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $user = User::query()->findOrFail($id);
        $messages = [];
        $result = null;

        if (isset($data['rank']) && (int) $data['rank'] !== (int) $user->rank) {
            $rank = (int) $data['rank'];
            abort_unless($this->ranks->all()->contains('id', $rank), 422, 'There is no such rank.');
            // Only someone above both the old and the new rank may move it.
            abort_unless((int) $user->rank < (int) $actor->rank && $rank < (int) $actor->rank, 403, 'You can only change ranks below your own.');
            $old = (int) $user->rank;
            $result = Hotel::send('setrank', ['user_id' => $id, 'rank' => $rank]);
            Audit::record('rank', sprintf('%s %s from %s to %s', $rank > $old ? 'promoted' : 'moved', $user->username, $this->ranks->name($old), $this->ranks->name($rank)), $id, ['rank' => $old], ['rank' => $rank], $data['reason'], $result);
            $messages[] = sprintf('%s is now %s', $user->username, $this->ranks->name($rank));
        }

        if (array_key_exists('team_id', $data) && $data['team_id'] !== null && (int) $data['team_id'] !== (int) $user->team_id) {
            $team = (int) $data['team_id'] === 0 ? null : (int) $data['team_id'];
            abort_if($team !== null && ! DB::table('website_teams')->where('id', $team)->exists(), 422, 'There is no such team.');
            $old = $user->team_id;
            DB::table('users')->where('id', $id)->update(['team_id' => $team]);
            Audit::record('team', sprintf('moved %s to %s', $user->username, $team !== null ? DB::table('website_teams')->where('id', $team)->value('rank_name') : 'no team'), $id, ['team_id' => $old], ['team_id' => $team], $data['reason']);
            $messages[] = 'Team saved';
        }

        if (array_key_exists('rotation', $data) && $data['rotation'] !== null && RoleplaySchema::has('rp_support_rotation')) {
            $on = (bool) $data['rotation'];
            $was = (bool) DB::table('rp_support_rotation')->where('user_id', $id)->value('available');

            if ($on !== $was) {
                // SupportUtility.SetAvailable: going available clears the missed count.
                DB::table('rp_support_rotation')->upsert(
                    [['user_id' => $id, 'available' => $on ? 1 : 0, 'last_offered_at' => 0, 'missed' => 0, 'updated_at' => time()]],
                    ['user_id'],
                    $on ? ['available', 'missed', 'updated_at'] : ['available', 'updated_at'],
                );
                Audit::record('rotation', $on ? sprintf('added %s to the support rotation', $user->username) : sprintf('took %s out of the support rotation', $user->username), $id, ['available' => $was], ['available' => $on], $data['reason']);
                $messages[] = $on ? 'In the support rotation' : 'Out of the support rotation';
            }
        }

        return response()->json([
            'path' => $result instanceof HotelResult ? 'live' : 'saved',
            'message' => $messages === [] ? 'Nothing changed' : implode(' · ', $messages),
        ]);
    }
}
