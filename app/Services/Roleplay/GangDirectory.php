<?php

namespace App\Services\Roleplay;

use App\Data\Roleplay\Gang;
use App\Data\Roleplay\GangMember;
use App\Data\Roleplay\Territory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Read model for the gangs: Habbo groups flagged is_gang, their members
 * (rp_gang_members) and the blocks they hold (rp_turfs). Standings are by
 * turf held, then level, then XP.
 */
class GangDirectory
{
    /** @var Collection<int, Gang>|null */
    private ?Collection $standings = null;

    public function available(): bool
    {
        return RoleplaySchema::has('groups')
            && RoleplaySchema::has('rp_gang_members')
            && RoleplaySchema::hasColumn('groups', 'is_gang');
    }

    /**
     * Every gang, ranked. Each carries its leader, member and online counts,
     * turf held and level.
     *
     * @return Collection<int, Gang>
     */
    public function standings(): Collection
    {
        if ($this->standings !== null) {
            return $this->standings;
        }

        if (! $this->available()) {
            return $this->standings = new Collection;
        }

        $members = DB::table('rp_gang_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->selectRaw('m.gang_id, COUNT(*) AS total, SUM(u.online = 1) AS online')
            ->groupBy('m.gang_id')
            ->get()
            ->keyBy('gang_id');

        $turf = RoleplaySchema::has('rp_turfs')
            ? DB::table('rp_turfs')->selectRaw('gang_id, COUNT(*) AS total')->groupBy('gang_id')->pluck('total', 'gang_id')
            : new Collection;

        $gangs = DB::table('groups as g')
            ->leftJoin('users as o', 'o.id', '=', 'g.owner_id')
            ->where('g.is_gang', '1')
            ->select(['g.id', 'g.name', 'g.desc', 'g.badge', 'g.owner_id', 'g.created', 'g.gang_level', 'g.gang_xp', 'o.username as owner_name', 'o.look as owner_look'])
            ->get()
            ->map(function (stdClass $gang) use ($members, $turf): Gang {
                $count = $members->get($gang->id);
                $total = $count !== null ? (int) $count->total : 0;

                return new Gang(
                    id: (int) $gang->id,
                    name: (string) $gang->name,
                    description: (string) ($gang->desc ?? ''),
                    badge: (string) ($gang->badge ?? ''),
                    initials: $this->initials((string) $gang->name),
                    owner_id: (int) $gang->owner_id,
                    leader: (string) ($gang->owner_name ?? ''),
                    leader_look: (string) ($gang->owner_look ?? ''),
                    created: (int) $gang->created,
                    level: (int) ($gang->gang_level ?? 1),
                    xp: (int) ($gang->gang_xp ?? 0),
                    members: $total,
                    online: $count !== null ? (int) $count->online : 0,
                    turf: (int) ($turf[$gang->id] ?? 0),
                    // the "Recruiting" tag: a gang still small enough to be looking
                    recruiting: $total < 10,
                );
            });

        $ranked = $gangs
            ->sortBy([['turf', 'desc'], ['level', 'desc'], ['xp', 'desc'], ['members', 'desc']])
            ->values();

        foreach ($ranked as $index => $gang) {
            $gang->rank = $index + 1;
        }

        return $this->standings = $ranked;
    }

    /**
     * Who holds which block: every claimed turf room with its gang.
     *
     * @return Collection<int, Territory>
     */
    public function territories(): Collection
    {
        if (! $this->available() || ! RoleplaySchema::has('rp_turfs')) {
            return new Collection;
        }

        return DB::table('rp_turfs as t')
            ->join('rooms as r', 'r.id', '=', 't.room_id')
            ->join('groups as g', 'g.id', '=', 't.gang_id')
            ->orderBy('g.name')
            ->orderBy('r.name')
            ->get(['r.id as room_id', 'r.name as district', 'g.id as gang_id', 'g.name as gang', 't.claimed_at'])
            ->map(fn (stdClass $row): Territory => new Territory(
                room_id: (int) $row->room_id,
                district: (string) $row->district,
                gang_id: (int) $row->gang_id,
                gang: (string) $row->gang,
                claimed_at: (int) $row->claimed_at,
            ))
            ->values();
    }

    /** The gang this player belongs to, with their role and the gang's standing. */
    public function membership(int $userId): ?Gang
    {
        if (! $this->available()) {
            return null;
        }

        $row = DB::table('rp_gang_members as m')
            ->leftJoin('rp_gang_roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.user_id', $userId)
            ->select(['m.gang_id', 'm.joined_at', 'r.name as role_name'])
            ->first();

        if ($row === null) {
            return null;
        }

        $standings = $this->standings();
        $gang = $standings->first(fn (Gang $gang): bool => $gang->id === (int) $row->gang_id);

        if ($gang === null) {
            return null;
        }

        $gang->role = $gang->owner_id === $userId ? __('Leader') : (string) ($row->role_name ?? __('Member'));
        $gang->joined_at = (int) $row->joined_at;
        $gang->total = $standings->count();

        return $gang;
    }

    /**
     * A couple of members' looks for the stacked heads on the home page.
     *
     * @return Collection<int, GangMember>
     */
    public function sampleMembers(int $gangId, int $limit = 3): Collection
    {
        if (! $this->available()) {
            return new Collection;
        }

        return DB::table('rp_gang_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.gang_id', $gangId)
            ->orderByDesc('u.online')
            ->orderByDesc('u.last_online')
            ->limit($limit)
            ->get(['u.id', 'u.username', 'u.look'])
            ->map(fn (stdClass $row): GangMember => new GangMember((int) $row->id, (string) $row->username, (string) $row->look))
            ->values();
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 2));
    }
}
