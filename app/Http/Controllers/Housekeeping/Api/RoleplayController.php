<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read model for the roleplay systems the emulator owns: corporations with
 * their ranks and employees, and gangs (Habbo groups flagged is_gang) with
 * their roles and members. Those writes must round-trip through RCON so the
 * emulator's in-memory managers stay in sync, so they are not here yet.
 *
 * Crimes are the exception and DO write directly. Nothing caches them: the
 * :charge command reads rp_crimes on every use, so an edit here is live on
 * the next charge with no emulator involvement at all.
 */
class RoleplayController extends Controller
{
    public function corporations(): JsonResponse
    {
        if (! $this->hasTable('rp_corporations')) {
            return response()->json(['available' => false, 'items' => []]);
        }

        $employees = DB::table('rp_corporation_employees')
            ->selectRaw('corporation_id, COUNT(*) AS total, SUM(on_duty) AS on_duty')
            ->groupBy('corporation_id')
            ->get()
            ->keyBy('corporation_id');

        $ranks = DB::table('rp_corporation_ranks')
            ->orderByDesc('rank_order')
            ->get()
            ->groupBy('corporation_id');

        $items = DB::table('rp_corporations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (object $corp) use ($employees, $ranks): array {
                $count = $employees->get($corp->id);

                return [
                    'id' => (int) $corp->id,
                    'name' => (string) $corp->name,
                    'acronym' => (string) ($corp->acronym ?? ''),
                    'description' => (string) $corp->description,
                    'badge' => (string) $corp->badge,
                    'service_type' => (string) ($corp->service_type ?? ''),
                    'stock' => (int) ($corp->stock ?? 0),
                    'stock_capacity' => (int) ($corp->stock_capacity ?? 0),
                    'manage_rank_order' => (int) ($corp->manage_rank_order ?? 0),
                    'employees' => $count !== null ? (int) $count->total : 0,
                    'on_duty' => $count !== null ? (int) $count->on_duty : 0,
                    'ranks' => collect($ranks->get($corp->id, collect()))->map(fn (object $rank): array => [
                        'id' => (int) $rank->id,
                        'order' => (int) $rank->rank_order,
                        'name' => (string) $rank->name,
                        'pay' => (int) $rank->pay,
                        'tiers' => (int) $rank->tiers,
                    ])->values()->all(),
                ];
            })
            ->values();

        return response()->json(['available' => true, 'items' => $items]);
    }

    public function corporation(int $id): JsonResponse
    {
        if (! $this->hasTable('rp_corporations')) {
            abort(404);
        }

        $corp = DB::table('rp_corporations')->where('id', $id)->first();

        if ($corp === null) {
            abort(404);
        }

        $employees = DB::table('rp_corporation_employees as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->leftJoin('rp_corporation_ranks as r', 'r.id', '=', 'e.rank_id')
            ->where('e.corporation_id', $id)
            ->select(['u.id', 'u.username', 'u.look', 'u.online', 'r.name as rank_name', 'r.rank_order', 'e.tier', 'e.hired_at', 'e.shift_seconds', 'e.shift_seconds_week', 'e.on_duty'])
            ->orderByDesc('r.rank_order')
            ->orderBy('u.username')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'username' => (string) $row->username,
                'look' => (string) $row->look,
                'online' => (string) $row->online === '1',
                'rank_name' => (string) ($row->rank_name ?? ''),
                'rank_order' => (int) ($row->rank_order ?? 0),
                'tier' => (int) $row->tier,
                'hired_at' => (int) $row->hired_at,
                'shift_seconds' => (int) $row->shift_seconds,
                'shift_seconds_week' => (int) $row->shift_seconds_week,
                'on_duty' => (int) $row->on_duty === 1,
            ])
            ->values();

        return response()->json([
            'corporation' => [
                'id' => (int) $corp->id,
                'name' => (string) $corp->name,
                'acronym' => (string) ($corp->acronym ?? ''),
                'description' => (string) $corp->description,
                'badge' => (string) $corp->badge,
                'service_type' => (string) ($corp->service_type ?? ''),
                'stock' => (int) ($corp->stock ?? 0),
                'stock_capacity' => (int) ($corp->stock_capacity ?? 0),
            ],
            'employees' => $employees,
        ]);
    }

    /**
     * The crime list behind :charge. `key_name` is what an officer types, so
     * it is the one field a rename cannot be careless about - changing it
     * changes the command, while the display name is free.
     */
    public function crimes(): JsonResponse
    {
        if (! $this->hasTable('rp_crimes')) {
            return response()->json(['available' => false, 'items' => []]);
        }

        $counts = DB::table('rp_charges')
            ->selectRaw('crime_id, COUNT(*) AS total')
            ->where('dropped_at', 0)
            ->groupBy('crime_id')
            ->pluck('total', 'crime_id');

        $items = DB::table('rp_crimes')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $crime): array => [
                'id' => (int) $crime->id,
                'key_name' => (string) $crime->key_name,
                'name' => (string) $crime->name,
                'description' => (string) $crime->description,
                'jail_seconds' => (int) $crime->jail_seconds,
                'stackable' => (int) $crime->stackable === 1,
                'active' => (int) $crime->active === 1,
                'sort_order' => (int) $crime->sort_order,
                'charges' => (int) ($counts[$crime->id] ?? 0),
            ])
            ->values();

        return response()->json(['available' => true, 'items' => $items]);
    }

    public function storeCrime(Request $request): JsonResponse
    {
        $data = $this->validateCrime($request);

        $data['sort_order'] = (int) (DB::table('rp_crimes')->max('sort_order') ?? 0) + 1;
        $id = DB::table('rp_crimes')->insertGetId($data);

        return response()->json(['id' => $id], 201);
    }

    public function updateCrime(Request $request, int $id): JsonResponse
    {
        if (DB::table('rp_crimes')->where('id', $id)->doesntExist()) {
            abort(404);
        }

        DB::table('rp_crimes')->where('id', $id)->update($this->validateCrime($request, $id));

        return response()->json(['ok' => true]);
    }

    /**
     * Retire rather than delete when the crime has history: a charge row
     * points at this id, and a rap sheet that cannot name what it is for is
     * worse than a crime nobody can pick any more.
     */
    public function destroyCrime(int $id): JsonResponse
    {
        if (DB::table('rp_crimes')->where('id', $id)->doesntExist()) {
            abort(404);
        }

        if (DB::table('rp_charges')->where('crime_id', $id)->exists()) {
            DB::table('rp_crimes')->where('id', $id)->update(['active' => 0]);

            return response()->json(['ok' => true, 'retired' => true]);
        }

        DB::table('rp_crimes')->where('id', $id)->delete();

        return response()->json(['ok' => true, 'retired' => false]);
    }

    /** @return array<string, mixed> */
    private function validateCrime(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:rp_crimes,key_name' . ($ignoreId !== null ? ',' . $ignoreId : '');

        $data = $request->validate([
            // lowercase letters and digits only: it is typed into chat, where a
            // space would end the argument and a capital would not match
            'key_name' => ['required', 'string', 'max:24', 'regex:/^[a-z0-9]+$/', $unique],
            'name' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            // an hour is already a long time to sit in a cell
            'jail_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'stackable' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ]);

        $data['description'] = $data['description'] ?? '';
        $data['stackable'] = $data['stackable'] ? 1 : 0;
        $data['active'] = $data['active'] ? 1 : 0;

        return $data;
    }

    public function gangs(): JsonResponse
    {
        if (! $this->hasTable('groups') || ! $this->hasTable('rp_gang_members') || ! Schema::hasColumn('groups', 'is_gang')) {
            return response()->json(['available' => false, 'items' => []]);
        }

        $members = DB::table('rp_gang_members')
            ->selectRaw('gang_id, COUNT(*) AS total')
            ->groupBy('gang_id')
            ->get()
            ->keyBy('gang_id');

        $items = DB::table('groups as g')
            ->leftJoin('users as o', 'o.id', '=', 'g.owner_id')
            ->where('g.is_gang', '1')
            ->select(['g.id', 'g.name', 'g.desc', 'g.badge', 'g.owner_id', 'g.created', 'g.gang_level', 'g.gang_xp', 'o.username as owner_name'])
            ->orderByDesc('g.gang_level')
            ->orderByDesc('g.gang_xp')
            ->get()
            ->map(function (object $gang) use ($members): array {
                $count = $members->get($gang->id);

                return [
                    'id' => (int) $gang->id,
                    'name' => (string) $gang->name,
                    'description' => (string) ($gang->desc ?? ''),
                    'badge' => (string) ($gang->badge ?? ''),
                    'owner_id' => (int) $gang->owner_id,
                    'owner_name' => (string) ($gang->owner_name ?? ''),
                    'created' => (int) $gang->created,
                    'level' => (int) ($gang->gang_level ?? 0),
                    'xp' => (int) ($gang->gang_xp ?? 0),
                    'members' => $count !== null ? (int) $count->total : 0,
                ];
            })
            ->values();

        return response()->json(['available' => true, 'items' => $items]);
    }

    public function gang(int $id): JsonResponse
    {
        if (! $this->hasTable('groups') || ! $this->hasTable('rp_gang_members')) {
            abort(404);
        }

        $gang = DB::table('groups as g')
            ->leftJoin('users as o', 'o.id', '=', 'g.owner_id')
            ->where('g.id', $id)
            ->where('g.is_gang', '1')
            ->select(['g.id', 'g.name', 'g.desc', 'g.badge', 'g.owner_id', 'g.created', 'g.gang_level', 'g.gang_xp', 'o.username as owner_name'])
            ->first();

        if ($gang === null) {
            abort(404);
        }

        $roles = DB::table('rp_gang_roles')
            ->where('gang_id', $id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $role): array => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'can_invite' => (string) $role->can_invite === '1',
                'can_kick' => (string) $role->can_kick === '1',
                'can_bank' => (string) $role->can_bank === '1',
                'is_admin' => (string) $role->is_admin === '1',
            ])
            ->values();

        $members = DB::table('rp_gang_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('rp_gang_roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.gang_id', $id)
            ->select(['u.id', 'u.username', 'u.look', 'u.online', 'r.name as role_name', 'm.joined_at'])
            ->orderBy('m.joined_at')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'username' => (string) $row->username,
                'look' => (string) $row->look,
                'online' => (string) $row->online === '1',
                'role_name' => (int) $row->id === (int) $gang->owner_id ? 'Leader' : (string) ($row->role_name ?? 'Member'),
                'joined_at' => (int) $row->joined_at,
            ])
            ->values();

        return response()->json([
            'gang' => [
                'id' => (int) $gang->id,
                'name' => (string) $gang->name,
                'description' => (string) ($gang->desc ?? ''),
                'badge' => (string) ($gang->badge ?? ''),
                'owner_id' => (int) $gang->owner_id,
                'owner_name' => (string) ($gang->owner_name ?? ''),
                'created' => (int) $gang->created,
                'level' => (int) ($gang->gang_level ?? 0),
                'xp' => (int) ($gang->gang_xp ?? 0),
            ],
            'roles' => $roles,
            'members' => $members,
        ]);
    }

    private function hasTable(string $table): bool
    {
        return (bool) Cache::remember('housekeeping:has-table:' . $table, now()->addMinutes(10), fn (): bool => Schema::hasTable($table));
    }
}
