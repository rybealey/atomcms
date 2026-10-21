<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteDrawBadge;
use App\Services\HousekeepingPermissionsService;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\BanScope;
use App\Support\Housekeeping\CityState;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Logs\LogSources;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\Pulse;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use stdClass;

/**
 * Today: what needs a human right now, then how the city is doing.
 */
class TodayController extends Controller
{
    public function __construct(private readonly HousekeepingPermissionsService $permissions) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $staffRank = (int) setting('min_staff_rank', 4);

        return response()->json([
            'now' => time(),
            'pulse' => [
                'online' => User::query()->where('online', '1')->count(),
                'staff_online' => User::query()->where('online', '1')->where('rank', '>=', $staffRank)->count(),
                'on_rotation' => RoleplaySchema::has('rp_support_rotation') ? DB::table('rp_support_rotation')->where('available', 1)->count() : 0,
                'emulator' => Pulse::current()['emulator'],
                'world' => CityState::current(),
            ],
            'attention' => $this->attention($actor),
            'jail' => $this->jail(),
            'shifts' => $this->shifts(),
            'turf' => $this->turf(),
            'registrations' => $this->registrations(),
            'online' => $this->onlineHistory(),
            'activity' => $this->permissions->allows($actor, 'view_activity_logs') ? $this->activity() : null,
        ]);
    }

    /**
     * The queue, most urgent first: unanswered 911 calls, support threads
     * waiting for staff, browser-guard flags, drawn badges, bans ending soon.
     *
     * @return list<array<string, mixed>>
     */
    private function attention(User $actor): array
    {
        $items = [];
        $now = time();

        if (RoleplaySchema::has('rp_911_calls')) {
            $calls = DB::table('rp_911_calls')
                ->where('kind', 0)
                ->where('responder_id', 0)
                ->where('created_at', '>=', $now - Pulse::CALL_WINDOW)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
            $refs = PlayerRefs::many($calls->pluck('caller_id'));

            foreach ($calls as $call) {
                $items[] = [
                    'key' => 'call-' . $call->id,
                    'kind' => 'call',
                    'tone' => 'bad',
                    'label' => 'Urgent',
                    'title' => '911 call with no responder',
                    'detail' => (string) $call->message,
                    'player' => PlayerRefs::pick($refs, $call->caller_id, (string) $call->caller_name),
                    'room' => ['id' => (int) $call->room_id, 'name' => (string) $call->room_name],
                    'at' => (int) $call->created_at,
                    'action' => 'Open call',
                    'to' => '/city/justice?call=' . $call->id,
                ];
            }
        }

        if (RoleplaySchema::has('rp_support_threads') && $this->permissions->allows($actor, 'manage_support')) {
            $threads = DB::table('rp_support_threads')
                ->whereIn('status', ['waiting', 'offered'])
                ->orderBy('created_at')
                ->limit(5)
                ->get();
            $refs = PlayerRefs::many($threads->pluck('player_id'));
            $firsts = RoleplaySchema::has('rp_support_messages') && $threads->isNotEmpty()
                ? DB::table('rp_support_messages')
                    ->whereIn('id', DB::table('rp_support_messages')->selectRaw('MIN(id)')->whereIn('thread_id', $threads->pluck('id'))->groupBy('thread_id'))
                    ->pluck('body', 'thread_id')
                : collect();

            foreach ($threads as $thread) {
                $items[] = [
                    'key' => 'support-' . $thread->id,
                    'kind' => 'support',
                    'tone' => 'warn',
                    'label' => 'Waiting',
                    'title' => 'Support thread · ' . ucfirst((string) $thread->category),
                    'detail' => (string) ($firsts->get($thread->id) ?? 'No message yet'),
                    'player' => PlayerRefs::pick($refs, $thread->player_id),
                    'room' => null,
                    'at' => (int) $thread->created_at,
                    'action' => 'Open thread',
                    'to' => '/moderation/support?thread=' . $thread->id,
                ];
            }
        }

        if (RoleplaySchema::has('rp_browser_guard_log') && $this->permissions->allows($actor, 'edit_user')) {
            $flags = DB::table('rp_browser_guard_log')
                ->where('created_at', '>=', $now - Pulse::GUARD_WINDOW)
                ->selectRaw('user_id, MAX(username) AS username, MAX(created_at) AS last_at, COUNT(*) AS hits, MAX(reason) AS reason')
                ->groupBy('user_id')
                ->orderByDesc('last_at')
                ->limit(3)
                ->get();
            $refs = PlayerRefs::many($flags->pluck('user_id'));

            foreach ($flags as $flag) {
                $items[] = [
                    'key' => 'guard-' . $flag->user_id,
                    'kind' => 'guard',
                    'tone' => 'info',
                    'label' => 'Review',
                    'title' => 'Browser-guard flag',
                    'detail' => sprintf('%s · %d %s in the last day', LogSources::guardReason((int) $flag->reason), (int) $flag->hits, (int) $flag->hits === 1 ? 'time' : 'times'),
                    'player' => PlayerRefs::pick($refs, $flag->user_id, (string) $flag->username),
                    'room' => null,
                    'at' => (int) $flag->last_at,
                    'action' => 'Review',
                    'to' => '/players/' . $flag->user_id . '/security',
                ];
            }
        }

        if ($this->permissions->allows($actor, 'manage_badges')) {
            $pending = WebsiteDrawBadge::query()->where('published', 0);
            $count = (clone $pending)->count();

            if ($count > 0) {
                $oldest = (clone $pending)->min('created_at');
                $items[] = [
                    'key' => 'badges',
                    'kind' => 'badge',
                    'tone' => 'brand',
                    'label' => 'Approval',
                    'title' => 'Drawn badges to approve',
                    'detail' => sprintf('%d waiting', $count),
                    'player' => null,
                    'room' => null,
                    'at' => Epoch::of($oldest),
                    'action' => 'Review',
                    'to' => '/content/badges',
                ];
            }
        }

        if ($this->permissions->allows($actor, 'manage_bans') && PlayerColumns::isPlus()) {
            $ending = DB::table('bans')
                ->where('bantype', 'user')
                ->where('expire', '>', $now)
                ->where('expire', '<=', $now + 86400)
                ->orderBy('expire')
                ->limit(4)
                ->get(['value', 'expire']);

            if ($ending->isNotEmpty()) {
                $items[] = [
                    'key' => 'bans',
                    'kind' => 'ban',
                    'tone' => '',
                    'label' => 'Today',
                    'title' => sprintf('%d %s expiring in 24 hours', BanScope::expiringWithin(86400), BanScope::expiringWithin(86400) === 1 ? 'ban' : 'bans'),
                    'detail' => $ending->map(fn (stdClass $ban): string => sprintf('%s at %s', $ban->value, Carbon::createFromTimestamp((int) $ban->expire)->format('g:i A')))->implode(' · '),
                    'player' => null,
                    'room' => null,
                    'at' => null,
                    'action' => 'View',
                    'to' => '/people/discipline?status=expiring',
                ];
            }
        }

        if ($this->permissions->allows($actor, 'view_activity_logs')) {
            foreach (SavedViewController::forToday($actor) as $view) {
                $items[] = [
                    'key' => 'view-' . $view['id'],
                    'kind' => 'view',
                    'tone' => 'info',
                    'label' => 'Saved view',
                    'title' => $view['name'],
                    'detail' => sprintf('%d %s today', $view['today'], $view['today'] === 1 ? 'match' : 'matches'),
                    'player' => null,
                    'room' => null,
                    'at' => null,
                    'action' => 'Open',
                    'to' => '/logs?' . http_build_query($view['filters'] + ['when' => '24h']),
                ];
            }
        }

        return $items;
    }

    /**
     * Everyone serving a sentence now. The clock only runs while the prisoner
     * is in the hotel, so seconds_left is as of the last minute they were.
     *
     * @return list<array<string, mixed>>
     */
    private function jail(): array
    {
        if (! RoleplaySchema::has('rp_jail')) {
            return [];
        }

        $rows = DB::table('rp_jail as j')
            ->leftJoin('users as u', 'u.id', '=', 'j.user_id')
            ->where('j.released_at', 0)
            ->orderBy('j.seconds_left')
            ->limit(8)
            ->get(['j.id', 'j.user_id', 'j.officer_id', 'j.room_id', 'j.sentence_seconds', 'j.seconds_left', 'j.jailed_at', 'j.charges', 'u.online']);
        $refs = PlayerRefs::many($rows->pluck('user_id')->merge($rows->pluck('officer_id')));

        return array_values($rows->map(fn (stdClass $row): array => [
            'id' => (int) $row->id,
            'player' => PlayerRefs::pick($refs, $row->user_id),
            'officer' => PlayerRefs::pick($refs, $row->officer_id),
            'charges' => LogSources::chargeLines((string) $row->charges),
            'sentence' => (int) $row->sentence_seconds,
            'left' => (int) $row->seconds_left,
            'online' => (string) $row->online === '1',
            'jailed_at' => (int) $row->jailed_at,
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shifts(): array
    {
        if (! RoleplaySchema::has('rp_corporation_employees') || ! RoleplaySchema::has('rp_corporations')) {
            return [];
        }

        $rows = DB::table('rp_corporations as c')
            ->join('rp_corporation_employees as e', 'e.corporation_id', '=', 'c.id')
            ->where('c.hidden', 0)
            ->groupBy('c.id', 'c.name', 'c.is_police', 'c.service_type')
            ->selectRaw('c.id, c.name, c.is_police, c.service_type, SUM(e.on_duty = 1) AS on_duty, COUNT(*) AS staff')
            ->havingRaw('SUM(e.on_duty = 1) > 0')
            ->orderByDesc('on_duty')
            ->limit(6)
            ->get();

        $ranks = RoleplaySchema::has('rp_corporation_ranks') && $rows->isNotEmpty()
            ? DB::table('rp_corporation_ranks')->whereIn('corporation_id', $rows->pluck('id'))->orderBy('rank_order')->get(['corporation_id', 'name'])->groupBy('corporation_id')
            : collect();

        return array_values($rows->map(function (stdClass $row) use ($ranks): array {
            $ladder = $ranks->get($row->id, collect());

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'domain' => CorporationController::domain((bool) $row->is_police, (string) $row->service_type),
                'ranks' => $ladder->count() > 1 ? sprintf('%s to %s', $ladder->first()?->name, strtolower((string) $ladder->last()?->name)) : (string) ($ladder->first()->name ?? ''),
                'on' => (int) $row->on_duty,
                'total' => (int) $row->staff,
            ];
        })->all());
    }

    /**
     * Turf claimed in the last day, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function turf(): array
    {
        if (! RoleplaySchema::has('rp_turfs') || ! RoleplaySchema::has('groups')) {
            return [];
        }

        $rows = DB::table('rp_turfs as t')
            ->join('groups as g', 'g.id', '=', 't.gang_id')
            ->where('t.claimed_at', '>=', time() - 86400)
            ->orderByDesc('t.claimed_at')
            ->limit(5)
            ->get(['t.room_id', 't.gang_id', 't.claimed_by', 't.claimed_at', 'g.name']);
        $rooms = RoomRefs::many($rows->pluck('room_id'));
        $refs = PlayerRefs::many($rows->pluck('claimed_by'));

        return array_values($rows->map(fn (stdClass $row): array => [
            'room' => ['id' => (int) $row->room_id, 'name' => $rooms[(int) $row->room_id] ?? 'Room ' . $row->room_id],
            'gang' => ['id' => (int) $row->gang_id, 'name' => (string) $row->name],
            'by' => PlayerRefs::pick($refs, $row->claimed_by),
            'at' => (int) $row->claimed_at,
        ])->all());
    }

    /**
     * New accounts per day for the last eight days, today last.
     *
     * @return list<array{day: int, count: int}>
     */
    private function registrations(): array
    {
        $start = Carbon::today()->subDays(7);
        $counts = User::query()
            ->where('account_created', '>=', (string) $start->timestamp)
            ->whereNull('parent_id')
            ->selectRaw('FLOOR((CAST(account_created AS UNSIGNED) - ?) / 86400) AS d, COUNT(*) AS n', [$start->timestamp])
            ->groupBy('d')
            ->pluck('n', 'd');

        $out = [];
        for ($i = 0; $i < 8; $i++) {
            $out[] = ['day' => (int) $start->copy()->addDays($i)->timestamp, 'count' => (int) ($counts->get($i) ?? 0)];
        }

        return $out;
    }

    /**
     * The highest online count each day for eight days, from the five-minute
     * samples, plus today's samples for the pulse sparkline.
     *
     * @return array{peaks: list<array{day: int, peak: int|null}>, recent: list<int>, since: int|null}
     */
    private function onlineHistory(): array
    {
        if (! RoleplaySchema::has('website_housekeeping_online_samples')) {
            return ['peaks' => [], 'recent' => [], 'since' => null];
        }

        $start = Carbon::today()->subDays(7);
        $peaks = DB::table('website_housekeeping_online_samples')
            ->where('sampled_at', '>=', $start)
            ->selectRaw('DATE(sampled_at) AS d, MAX(online) AS peak')
            ->groupBy('d')
            ->pluck('peak', 'd');

        $out = [];
        for ($i = 0; $i < 8; $i++) {
            $day = $start->copy()->addDays($i);
            $peak = $peaks->get($day->toDateString());
            $out[] = ['day' => (int) $day->timestamp, 'peak' => $peak !== null ? (int) $peak : null];
        }

        $recent = array_values(DB::table('website_housekeeping_online_samples')
            ->where('sampled_at', '>=', now()->subHours(8))
            ->orderBy('sampled_at')
            ->pluck('online')
            ->map(fn (mixed $n): int => (int) $n)
            ->all());

        return ['peaks' => $out, 'recent' => $recent, 'since' => Epoch::of(DB::table('website_housekeeping_online_samples')->min('sampled_at')) ?: null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activity(): array
    {
        return array_values(Activity::query()
            ->where('log_name', Audit::LOG)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (Activity $entry): array => AuditController::row($entry))
            ->all());
    }
}
