<?php

namespace App\Http\Controllers\Housekeeping\Api;

use App\Http\Controllers\Controller;
use App\Services\Roleplay\RoleplaySchema;
use App\Support\Housekeeping\PlayerRefs;
use App\Support\Housekeeping\RoomRefs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Gangs are Habbo groups with is_gang set; roles carry invite, kick, bank and
 * admin rights and turf is a room claim. Changed in game; read here.
 */
class GangController extends Controller
{
    /** GangManager.XpCap: the XP a level needs before the next one. */
    public static function xpCap(int $level): int
    {
        return max(1, $level) * 200;
    }

    public function index(): JsonResponse
    {
        if (! RoleplaySchema::hasColumn('groups', 'is_gang')) {
            return response()->json(['items' => []]);
        }

        $members = RoleplaySchema::has('rp_gang_members')
            ? DB::table('rp_gang_members as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->groupBy('m.gang_id')->selectRaw("m.gang_id, COUNT(*) AS n, SUM(u.online = '1') AS online")->get()->keyBy('gang_id')
            : collect();
        $turf = RoleplaySchema::has('rp_turfs') ? DB::table('rp_turfs')->groupBy('gang_id')->selectRaw('gang_id, COUNT(*) AS n')->pluck('n', 'gang_id') : collect();
        $gangs = DB::table('groups')->where('is_gang', '1')->orderByDesc('gang_level')->orderByDesc('gang_xp')->get(['id', 'name', 'badge', 'owner_id', 'created', 'gang_level', 'gang_xp']);
        $refs = PlayerRefs::many($gangs->pluck('owner_id'));

        return response()->json([
            'items' => $gangs->map(fn (stdClass $g): array => [
                'id' => (int) $g->id,
                'name' => (string) $g->name,
                'badge' => (string) $g->badge,
                'boss' => PlayerRefs::pick($refs, $g->owner_id),
                'created' => (int) $g->created,
                'level' => (int) $g->gang_level,
                'xp' => (int) $g->gang_xp,
                'xp_cap' => self::xpCap((int) $g->gang_level),
                'members' => (int) ($members->get($g->id)->n ?? 0),
                'online' => (int) ($members->get($g->id)->online ?? 0),
                'turf' => (int) ($turf->get($g->id) ?? 0),
            ])->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        abort_unless(RoleplaySchema::hasColumn('groups', 'is_gang'), 404);
        $g = DB::table('groups')->where('id', $id)->where('is_gang', '1')->first();
        abort_if($g === null, 404);

        $roles = RoleplaySchema::has('rp_gang_roles') ? DB::table('rp_gang_roles')->where('gang_id', $id)->orderBy('sort_order')->get() : collect();
        $members = RoleplaySchema::has('rp_gang_members')
            ? DB::table('rp_gang_members as m')->leftJoin('users as u', 'u.id', '=', 'm.user_id')->where('m.gang_id', $id)->orderBy('m.joined_at')->get(['m.user_id', 'm.role_id', 'm.joined_at', 'u.online'])
            : collect();
        $ids = $members->pluck('user_id');
        $jailedNow = RoleplaySchema::has('rp_jail') && $ids->isNotEmpty() ? DB::table('rp_jail')->whereIn('user_id', $ids)->where('released_at', 0)->pluck('user_id')->flip() : collect();
        $jailedWeek = RoleplaySchema::has('rp_jail') && $ids->isNotEmpty() ? DB::table('rp_jail')->whereIn('user_id', $ids)->where('jailed_at', '>=', time() - 7 * 86400)->groupBy('user_id')->selectRaw('user_id, COUNT(*) AS n')->pluck('n', 'user_id') : collect();
        $turf = RoleplaySchema::has('rp_turfs') ? DB::table('rp_turfs')->where('gang_id', $id)->orderByDesc('claimed_at')->get() : collect();
        $invites = RoleplaySchema::has('rp_gang_invites') ? DB::table('rp_gang_invites')->where('gang_id', $id)->where('expires_at', '>', time())->orderByDesc('expires_at')->get() : collect();
        $refs = PlayerRefs::many($ids->merge([$g->owner_id])->merge($turf->pluck('claimed_by'))->merge($invites->pluck('user_id'))->merge($invites->pluck('invited_by')));
        $rooms = RoomRefs::many($turf->pluck('room_id'));
        $roleNames = $roles->pluck('name', 'id');
        $perRole = $members->groupBy('role_id')->map->count();

        return response()->json([
            'gang' => [
                'id' => (int) $g->id,
                'name' => (string) $g->name,
                'description' => (string) $g->desc,
                'badge' => (string) $g->badge,
                'boss' => PlayerRefs::pick($refs, $g->owner_id),
                'created' => (int) $g->created,
                'level' => (int) $g->gang_level,
                'xp' => (int) $g->gang_xp,
                'xp_cap' => self::xpCap((int) $g->gang_level),
                'room_id' => (int) $g->room_id,
            ],
            'stats' => [
                'members' => $members->count(),
                'online' => $members->where('online', '1')->count(),
                'turf' => $turf->count(),
                'contested' => $turf->where('claimed_at', '>=', time() - 3600)->count(),
                'arrests_week' => (int) $jailedWeek->sum(),
                'arrested_members' => $jailedWeek->count(),
                'invites' => $invites->count(),
            ],
            'roles' => $roles->map(fn (stdClass $r): array => [
                'id' => (int) $r->id,
                'name' => (string) $r->name,
                'invite' => (string) $r->can_invite === '1',
                'kick' => (string) $r->can_kick === '1',
                'bank' => (string) $r->can_bank === '1',
                'admin' => (string) $r->is_admin === '1',
                'members' => (int) ($perRole->get($r->id) ?? 0),
            ])->values(),
            'members' => $members->map(fn (stdClass $m): array => [
                'player' => PlayerRefs::pick($refs, $m->user_id),
                'role' => (int) $m->user_id === (int) $g->owner_id ? 'Boss' : (string) ($roleNames->get($m->role_id) ?? 'Member'),
                'status' => $jailedNow->has($m->user_id) ? 'jailed' : ((string) $m->online === '1' ? 'online' : 'offline'),
                'joined_at' => (int) $m->joined_at,
                'jailed_week' => (int) ($jailedWeek->get($m->user_id) ?? 0),
            ])->values(),
            'turf' => $turf->map(fn (stdClass $t): array => [
                'room' => ['id' => (int) $t->room_id, 'name' => $rooms[(int) $t->room_id] ?? 'Room ' . $t->room_id],
                'claimed_at' => (int) $t->claimed_at,
                'by' => PlayerRefs::pick($refs, $t->claimed_by),
                'contested' => (int) $t->claimed_at >= time() - 3600,
            ])->values(),
            'invites' => $invites->map(fn (stdClass $i): array => [
                'player' => PlayerRefs::pick($refs, $i->user_id),
                'by' => PlayerRefs::pick($refs, $i->invited_by),
                'expires_at' => (int) $i->expires_at,
            ])->values(),
        ]);
    }
}
