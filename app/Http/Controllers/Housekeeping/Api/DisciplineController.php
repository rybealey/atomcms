<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\Audit;
use App\Support\Housekeeping\Epoch;
use App\Support\Housekeeping\Hotel;
use App\Support\Housekeeping\PlayerColumns;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\Pulse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Discipline: every ban and mute across the hotel in one list - account,
 * IP and machine bans from PlusEMU's bans table, mutes from the users rows
 * and the panel's own audit trail.
 */
class DisciplineController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:active,soon,history'],
            'staff' => ['nullable', 'string', 'max:64'],
            'scope' => ['nullable', 'in:user,ip,machine,mute'],
            'q' => ['nullable', 'string', 'max:64'],
        ]);
        abort_unless(PlayerColumns::isPlus(), 404, 'Discipline reads the PlusEMU bans table.');
        $now = time();
        $view = (string) ($validated['view'] ?? 'active');
        $q = trim((string) ($validated['q'] ?? ''));
        $scope = $validated['scope'] ?? null;

        $bans = DB::table('bans')
            ->when($view === 'active', fn (Builder $b) => $b->where('expire', '>', $now))
            ->when($view === 'soon', fn (Builder $b) => $b->where('expire', '>', $now)->where('expire', '<=', $now + 86400))
            ->when($view === 'history', fn (Builder $b) => $b->where('expire', '<=', $now))
            ->when(isset($validated['staff']) && $validated['staff'] !== '', fn (Builder $b) => $b->where('added_by', $validated['staff']))
            ->when($scope !== null && $scope !== 'mute', fn (Builder $b) => $b->where('bantype', $scope))
            ->when($scope === 'mute', fn (Builder $b) => $b->whereRaw('1 = 0'))
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('value', 'like', '%' . addcslashes($q, '\\%_') . '%')->orWhere('reason', 'like', '%' . addcslashes($q, '\\%_') . '%')))
            ->orderByRaw($view === 'history' ? 'expire DESC' : 'expire ASC')
            ->limit(200)
            ->get();

        // Who each ban lands on: the user by name, or everyone on the IP or machine.
        $names = $bans->where('bantype', 'user')->pluck('value')->all();
        $byName = $names !== [] ? DB::table('users')->whereIn('username', $names)->get(['id', 'username', 'look', 'parent_id'])->keyBy(fn (stdClass $u): string => strtolower((string) $u->username)) : collect();
        $ipUsers = DB::table('users')->whereIn(PlayerColumns::currentIp(), $bans->where('bantype', 'ip')->pluck('value'))->get(['id', 'username', 'look', PlayerColumns::currentIp() . ' as ip'])->groupBy('ip');
        $machineUsers = RoleplaySchema::hasColumn('users', 'machine_id') ? DB::table('users')->whereIn('machine_id', $bans->where('bantype', 'machine')->pluck('value'))->get(['id', 'username', 'look', 'machine_id'])->groupBy('machine_id') : collect();
        $roots = $byName->map(fn (stdClass $u): int => (int) ($u->parent_id ?: $u->id))->unique()->values();
        $characterCounts = $roots->isNotEmpty() ? DB::table('users')->whereIn('parent_id', $roots)->groupBy('parent_id')->selectRaw('parent_id, COUNT(*) + 1 AS n')->pluck('n', 'parent_id') : collect();

        $rows = [];

        foreach ($bans as $ban) {
            $target = null;
            $others = [];

            if ($ban->bantype === 'user') {
                $u = $byName->get(strtolower((string) $ban->value));
                $target = $u !== null ? ['id' => (int) $u->id, 'username' => (string) $u->username, 'look' => (string) $u->look] : ['id' => 0, 'username' => (string) $ban->value, 'look' => ''];
                $chars = $u !== null ? (int) ($characterCounts->get((int) ($u->parent_id ?: $u->id)) ?? 1) : 1;
            } else {
                $found = $ban->bantype === 'ip' ? $ipUsers->get($ban->value, collect()) : $machineUsers->get($ban->value, collect());
                $first = $found->first();
                $target = $first !== null ? ['id' => (int) $first->id, 'username' => (string) $first->username, 'look' => (string) $first->look] : null;
                $others = $found->skip(1)->pluck('username')->all();
                $chars = $found->count();
            }

            $rows[] = [
                'key' => 'ban-' . $ban->id,
                'id' => (int) $ban->id,
                'type' => 'ban',
                'scope' => (string) $ban->bantype,
                'value' => $ban->bantype === 'user' ? null : (string) $ban->value,
                'player' => $target,
                'also' => $others,
                'characters' => $chars,
                'reason' => (string) $ban->reason,
                'staff' => (string) $ban->added_by,
                'started' => (int) $ban->added_date,
                'ends' => (int) $ban->expire,
                'active' => (float) $ban->expire > $now,
            ];
        }

        if ($scope === null || $scope === 'mute') {
            $mutes = $view === 'history'
                ? collect()
                : DB::table('users')->where('time_muted', '>', 0)
                    ->when($view === 'soon', fn (Builder $b) => $b->where('time_muted', '<=', 86400))
                    ->when($q !== '', fn (Builder $b) => $b->where('username', 'like', '%' . addcslashes($q, '\\%_') . '%'))
                    ->orderBy('time_muted')->limit(100)->get(['id', 'username', 'look', 'time_muted', 'online']);
            $muteAudit = $mutes->isNotEmpty()
                ? DB::table('activity_log')->where('log_name', Audit::LOG)->where('event', 'mute')->where('subject_type', User::class)->whereIn('subject_id', $mutes->pluck('id')->map(fn (mixed $id): string => (string) $id))->orderByDesc('id')->get()->unique('subject_id')->keyBy('subject_id')
                : collect();
            $causers = PlayerRefs::many($muteAudit->pluck('causer_id'));

            foreach ($mutes as $m) {
                $a = $muteAudit->get((string) $m->id);
                /** @var array<string, mixed> $props */
                $props = $a !== null ? (json_decode((string) $a->properties, true) ?: []) : [];
                $rows[] = [
                    'key' => 'mute-' . $m->id,
                    'id' => (int) $m->id,
                    'type' => 'mute',
                    'scope' => 'user',
                    'value' => null,
                    'player' => ['id' => (int) $m->id, 'username' => (string) $m->username, 'look' => (string) $m->look],
                    'also' => [],
                    'characters' => 1,
                    'reason' => (string) ($props['reason'] ?? 'Muted in game'),
                    'staff' => $a !== null ? ($causers[(int) $a->causer_id]['username'] ?? 'Staff') : 'In game',
                    'started' => $a !== null ? Epoch::of($a->created_at) : null,
                    // The mute clock only runs while the player is online.
                    'ends' => null,
                    'left' => (int) $m->time_muted,
                    'active' => true,
                ];
            }
        }

        $week = $now - 7 * 86400;
        $active = DB::table('bans')->where('expire', '>', $now)->groupBy('bantype')->selectRaw('bantype, COUNT(*) AS n')->pluck('n', 'bantype');
        $soon = DB::table('bans')->where('expire', '>', $now)->where('expire', '<=', $now + 86400)->orderBy('expire')->pluck('value');

        return response()->json([
            'rows' => $rows,
            'stats' => [
                'bans' => ['user' => (int) ($active['user'] ?? 0), 'ip' => (int) ($active['ip'] ?? 0), 'machine' => (int) ($active['machine'] ?? 0)],
                'mutes' => (int) DB::table('users')->where('time_muted', '>', 0)->count(),
                'longest_mute' => (int) DB::table('users')->max('time_muted'),
                'ending_soon' => $soon->count(),
                'ending_names' => $soon->take(3)->values(),
                'issued_week' => DB::table('bans')->where('added_date', '>=', (string) $week)->count(),
                'mutes_week' => DB::table('activity_log')->where('log_name', Audit::LOG)->where('event', 'mute')->where('created_at', '>=', date('Y-m-d H:i:s', $week))->count(),
                'lifted_week' => DB::table('activity_log')->where('log_name', Audit::LOG)->where('event', 'unban')->where('created_at', '>=', date('Y-m-d H:i:s', $week))->count(),
            ],
            'counts' => [
                'active' => DB::table('bans')->where('expire', '>', $now)->count() + (int) DB::table('users')->where('time_muted', '>', 0)->count(),
                'soon' => $soon->count(),
                'history' => DB::table('bans')->where('expire', '<=', $now)->count(),
            ],
            'staff' => DB::table('bans')->distinct()->orderBy('added_by')->pluck('added_by')->filter()->values(),
        ]);
    }

    /**
     * Lift one ban row early - an IP or machine ban, or one character's.
     */
    public function lift(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $ban = DB::table('bans')->where('id', $id)->first();
        abort_if($ban === null, 404);
        abort_unless((float) $ban->expire > time(), 422, 'This ban has already ended.');
        DB::table('bans')->where('id', $id)->update(['expire' => time()]);
        $reload = Hotel::reload('bans');
        // The player the ban lands on: by name, or the first account on the IP or machine.
        $player = match ((string) $ban->bantype) {
            'user' => DB::table('users')->where('username', $ban->value)->value('id'),
            'ip' => DB::table('users')->where(PlayerColumns::currentIp(), $ban->value)->value('id'),
            default => RoleplaySchema::hasColumn('users', 'machine_id') ? DB::table('users')->where('machine_id', $ban->value)->value('id') : null,
        };
        Audit::record('unban', sprintf('lifted the %s ban on %s', $ban->bantype === 'user' ? 'account' : $ban->bantype, $ban->value), $player !== null ? (int) $player : null, ['until' => (int) $ban->expire], ['until' => time()], $data['reason'], $reload, ['type' => 'ban', 'id' => $id, 'label' => (string) $ban->value]);
        Pulse::forget();

        return response()->json(['message' => 'Ban lifted', 'path' => 'saved', 'reloaded' => $reload->sent]);
    }
}
