<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\CityState;
use App\Support\Housekeeping\PlayerRefs;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Rooms and world: the city-wide switches and every room with a roleplay
 * role - an HQ, turf, the jail, an arrest room or a safe zone. The flags are
 * set in game (the Room tool's Gameplay tab).
 */
class RoomController extends Controller
{
    private const ROLES = ['hq', 'turf', 'jail', 'arrest', 'safe'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:all,hq,turf,jail,arrest,safe,any'],
            'q' => ['nullable', 'string', 'max:64'],
            'room' => ['nullable', 'integer', 'min:1'],
        ]);
        $title = RoleplaySchema::roomTitleColumn();
        $role = (string) ($validated['role'] ?? 'all');
        $q = trim((string) ($validated['q'] ?? ''));
        $hasFlags = RoleplaySchema::hasColumn('rooms', 'is_turf');

        $base = DB::table('rooms');

        if ($hasFlags) {
            $base->where(fn (Builder $w) => $w->where('corporation_id', '>', 0)->orWhere('is_turf', '1')->orWhere('rp_jail_room', '1')->orWhere('rp_arrest_room', '1')->orWhere('is_safe_zone', '1'));
        }

        $counts = ['all' => (clone $base)->count()];

        if ($hasFlags) {
            foreach (self::ROLES as $r) {
                $counts[$r] = $this->whereRole(clone $base, $r)->count();
            }
        }

        $query = clone $base;

        if ($role !== 'all' && $role !== 'any' && $hasFlags) {
            $this->whereRole($query, $role);
        }

        if ($q !== '' || isset($validated['room'])) {
            // A search or a linked room looks across every room, roleplay role or not.
            $query = DB::table('rooms')->where(function (Builder $w) use ($q, $title, $validated): void {
                if ($q !== '') {
                    $w->where($title, 'like', '%' . addcslashes($q, '\\%_') . '%');

                    if (ctype_digit($q)) {
                        $w->orWhere('id', (int) $q);
                    }
                }

                if (isset($validated['room'])) {
                    $w->orWhere('id', (int) $validated['room']);
                }
            });
        }

        $rooms = $query->orderByDesc('users_now')->orderBy('id')->limit(200)->get();
        $corps = RoleplaySchema::has('rp_corporations') ? DB::table('rp_corporations')->pluck('name', 'id') : collect();
        $turf = RoleplaySchema::has('rp_turfs') && $rooms->isNotEmpty()
            ? DB::table('rp_turfs as t')->join('groups as g', 'g.id', '=', 't.gang_id')->whereIn('t.room_id', $rooms->pluck('id'))->get(['t.room_id', 't.gang_id', 'g.name', 't.claimed_at'])->keyBy('room_id')
            : collect();

        return response()->json([
            'world' => $this->world(),
            'counts' => $counts,
            'rooms' => $rooms->map(fn (stdClass $r): array => $this->row($r, $title, $corps, $turf))->values(),
        ]);
    }

    /**
     * One room in more detail, for the side panel.
     */
    public function show(int $id): JsonResponse
    {
        $title = RoleplaySchema::roomTitleColumn();
        $room = DB::table('rooms')->where('id', $id)->first();
        abort_if($room === null, 404);
        $corps = RoleplaySchema::has('rp_corporations') ? DB::table('rp_corporations')->pluck('name', 'id') : collect();
        $turf = RoleplaySchema::has('rp_turfs') ? DB::table('rp_turfs as t')->join('groups as g', 'g.id', '=', 't.gang_id')->where('t.room_id', $id)->get(['t.room_id', 't.gang_id', 'g.name', 't.claimed_at', 't.claimed_by'])->keyBy('room_id') : collect();
        $hq = RoleplaySchema::has('rp_hq_room_ranks') ? DB::table('rp_hq_room_ranks as h')->join('rp_corporation_ranks as r', 'r.id', '=', 'h.rank_id')->where('h.room_id', $id)->orderBy('r.rank_order')->pluck('r.name') : collect();
        $owner = DB::table('users')->where('username', $room->owner)->first(['id', 'username', 'look']);
        $claimer = $turf->get($id);
        $refs = PlayerRefs::many([$claimer->claimed_by ?? 0]);

        return response()->json($this->row($room, $title, $corps, $turf) + [
            'description' => (string) $room->description,
            'owner_ref' => $owner !== null ? ['id' => (int) $owner->id, 'username' => (string) $owner->username, 'look' => (string) $owner->look] : null,
            'state' => (string) $room->state,
            'max' => (int) $room->users_max,
            'furni' => (int) DB::table('items')->where('room_id', $id)->count(),
            'hq_ranks' => $hq->values(),
            'claimed_by' => $claimer !== null ? PlayerRefs::pick($refs, $claimer->claimed_by) : null,
            'chat_today' => RoleplaySchema::has('chatlogs') ? DB::table('chatlogs')->where('room_id', $id)->where('timestamp', '>=', time() - 86400)->count() : 0,
            'bans' => RoleplaySchema::has('room_bans') ? DB::table('room_bans')->where('room_id', $id)->where('expire', '>', time())->count() : 0,
        ]);
    }

    private function whereRole(Builder $query, string $role): Builder
    {
        return match ($role) {
            'hq' => $query->where('corporation_id', '>', 0),
            'turf' => $query->where('is_turf', '1'),
            'jail' => $query->where('rp_jail_room', '1'),
            'arrest' => $query->where('rp_arrest_room', '1'),
            'safe' => $query->where('is_safe_zone', '1'),
            default => $query,
        };
    }

    /**
     * @param  Collection<int|string, mixed>  $corps
     * @param  Collection<int|string, stdClass>  $turf
     *
     * @return array<string, mixed>
     */
    private function row(stdClass $r, string $title, $corps, $turf): array
    {
        $flag = fn (string $column): bool => isset($r->{$column}) && (string) $r->{$column} === '1';
        $roles = array_values(array_filter([
            (int) ($r->corporation_id ?? 0) > 0 ? 'hq' : null,
            $flag('is_turf') ? 'turf' : null,
            $flag('rp_jail_room') ? 'jail' : null,
            $flag('rp_arrest_room') ? 'arrest' : null,
            $flag('is_safe_zone') ? 'safe' : null,
        ]));
        $claim = $turf->get($r->id);

        return [
            'id' => (int) $r->id,
            'name' => (string) $r->{$title},
            'owner' => (string) $r->owner,
            'now' => (int) $r->users_now,
            'roles' => $roles,
            'corporation' => (int) ($r->corporation_id ?? 0) > 0 ? ['id' => (int) $r->corporation_id, 'name' => (string) ($corps->get($r->corporation_id) ?? 'Corporation ' . $r->corporation_id)] : null,
            'gang' => $claim !== null ? ['id' => (int) $claim->gang_id, 'name' => (string) $claim->name, 'claimed_at' => (int) $claim->claimed_at, 'contested' => (int) $claim->claimed_at >= time() - 3600] : null,
            'access' => array_values(array_filter([$flag('allow_police') ? 'Police' : null, $flag('allow_medical') ? 'Medical' : null, $flag('allow_staff') ? 'Staff' : null])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function world(): array
    {
        $w = CityState::current();

        return [
            ['label' => 'Maintenance', 'state' => $w['maintenance'] ? 'On' : 'Off', 'pill' => $w['maintenance'] ? 'Staff only' : 'Normal', 'tone' => $w['maintenance'] ? 'warn' : 'ok', 'detail' => $w['maintenance'] ? 'Players below staff are kept out' : 'The hotel is open', 'key' => 'hotel.maintenance'],
            ['label' => 'Combat', 'state' => $w['combat_paused'] ? 'Paused' : 'Active', 'pill' => $w['combat_paused'] ? 'Paused' : 'Not paused', 'tone' => $w['combat_paused'] ? 'warn' : 'ok', 'detail' => $w['combat_paused'] ? 'Hit, stun, push and pull are off' : 'Fighting works as normal', 'key' => 'hotel.combat_paused'],
            ['label' => 'Weather', 'state' => $w['weather']['pinned'] ? (string) $w['weather']['name'] : 'Live', 'pill' => $w['weather']['pinned'] ? 'Override' : 'Synced', 'tone' => $w['weather']['pinned'] ? 'warn' : 'ok', 'detail' => $w['weather']['pinned'] ? 'Pinned in the City Panel' : 'Follows the real San Francisco weather', 'key' => 'city.weather.*'],
            ['label' => 'City time', 'state' => $w['time']['phase'], 'pill' => $w['time']['pinned'] ? 'Pinned' : 'Synced', 'tone' => $w['time']['pinned'] ? 'warn' : 'ok', 'detail' => $w['time']['pinned'] ? 'Held at ' . $w['time']['label'] : 'Now ' . $w['time']['label'] . ' on the city clock', 'key' => 'city.time.*'],
        ];
    }
}
