<?php

namespace App\Services\Roleplay;

use App\Data\Roleplay\Corporation;
use App\Data\Roleplay\CorporationRank;
use App\Data\Roleplay\Employee;
use App\Data\Roleplay\Employment;
use App\Data\Roleplay\HqRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Read model for the city's corporations: rp_corporations, their ranks and
 * their employees. The emulator owns every write (hiring, promotion, shifts),
 * so this only ever reads.
 */
class CorporationDirectory
{
    public function available(): bool
    {
        return RoleplaySchema::has('rp_corporations');
    }

    /**
     * Every visible corporation with its headcount, who is on shift and its
     * positions, highest rank first.
     *
     * @return Collection<int, Corporation>
     */
    public function all(): Collection
    {
        if (! $this->available()) {
            return new Collection;
        }

        $counts = DB::table('rp_corporation_employees')
            ->selectRaw('corporation_id, COUNT(*) AS total, SUM(on_duty) AS on_duty')
            ->groupBy('corporation_id')
            ->get()
            ->keyBy('corporation_id');

        $ranks = DB::table('rp_corporation_ranks')
            ->orderByDesc('rank_order')
            ->get()
            ->groupBy('corporation_id');

        $query = DB::table('rp_corporations')->orderBy('sort_order')->orderBy('id');

        if (RoleplaySchema::hasColumn('rp_corporations', 'hidden')) {
            $query->where('hidden', 0);
        }

        return $query->get()->map(function (stdClass $corp) use ($counts, $ranks): Corporation {
            $count = $counts->get($corp->id);
            /** @var Collection<int, stdClass> $corpRanks */
            $corpRanks = $ranks->get($corp->id, new Collection);

            return $this->shape($corp, $corpRanks, $count !== null ? (int) $count->total : 0, $count !== null ? (int) $count->on_duty : 0);
        })->values();
    }

    /**
     * One corporation with its ranks, each rank's employees, who is on shift
     * and the HQ rooms the ranks unlock.
     */
    public function find(int $id): ?Corporation
    {
        if (! $this->available()) {
            return null;
        }

        $corp = DB::table('rp_corporations')->where('id', $id)->first();

        if ($corp === null || (RoleplaySchema::hasColumn('rp_corporations', 'hidden') && (int) ($corp->hidden ?? 0) === 1)) {
            return null;
        }

        $ranks = DB::table('rp_corporation_ranks')->where('corporation_id', $id)->orderByDesc('rank_order')->get();

        $employees = DB::table('rp_corporation_employees as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.corporation_id', $id)
            ->select(['u.id', 'u.username', 'u.look', 'u.online', 'e.rank_id', 'e.tier', 'e.on_duty', 'e.hired_at', 'e.shift_seconds'])
            ->orderByDesc('e.on_duty')
            ->orderBy('u.username')
            ->get()
            ->map(fn (stdClass $row): Employee => new Employee(
                id: (int) $row->id,
                username: (string) $row->username,
                look: (string) $row->look,
                online: (string) $row->online === '1',
                rank_id: (int) $row->rank_id,
                tier: (int) $row->tier,
                on_duty: (int) $row->on_duty === 1,
                hired_at: (int) $row->hired_at,
                shift_seconds: (int) $row->shift_seconds,
            ));

        $byRank = $employees->groupBy('rank_id');
        $ranksById = $ranks->keyBy('id');
        $onDuty = $employees->filter(fn (Employee $employee): bool => $employee->on_duty);

        $corporation = $this->shape($corp, $ranks, $employees->count(), $onDuty->count());

        foreach ($corporation->ranks as $rank) {
            /** @var Collection<int, Employee> $staff */
            $staff = $byRank->get($rank->id, new Collection);
            $rank->employees = $staff->values();
            $rank->count = $staff->count();
        }

        $corporation->on_shift = $onDuty->map(function (Employee $employee) use ($ranksById): Employee {
            $rank = $ranksById->get($employee->rank_id);
            $employee->rank_name = $rank !== null ? (string) $rank->name : '';

            return $employee;
        })->values();

        $top = $ranks->first();

        if ($top !== null) {
            /** @var Collection<int, Employee> $topStaff */
            $topStaff = $byRank->get($top->id, new Collection);
            $corporation->head = $topStaff->first();
            $corporation->head_rank = (string) $top->name;
        }

        if (RoleplaySchema::has('rp_hq_room_ranks')) {
            $corporation->hq_rooms = DB::table('rp_hq_room_ranks as h')
                ->join('rooms as r', 'r.id', '=', 'h.room_id')
                ->whereIn('h.rank_id', $ranks->pluck('id')->all())
                ->distinct()
                ->orderBy('r.id')
                ->get(['r.id', 'r.name'])
                ->map(fn (stdClass $room): HqRoom => new HqRoom((int) $room->id, (string) $room->name))
                ->values();
        }

        return $corporation;
    }

    /**
     * Where this player works: the corporation, their rank, the next rank up
     * and how far along the current rank's tiers they are.
     */
    public function employment(int $userId): ?Employment
    {
        if (! $this->available()) {
            return null;
        }

        $row = DB::table('rp_corporation_employees as e')
            ->join('rp_corporations as c', 'c.id', '=', 'e.corporation_id')
            ->leftJoin('rp_corporation_ranks as r', 'r.id', '=', 'e.rank_id')
            ->where('e.user_id', $userId)
            ->select(['c.id', 'c.name', 'c.badge', 'c.service_type', 'r.id as rank_id', 'r.name as rank_name', 'r.pay', 'r.tiers', 'r.rank_order', 'e.tier', 'e.on_duty', 'e.shift_seconds', 'e.hired_at'])
            ->first();

        if ($row === null) {
            return null;
        }

        $next = DB::table('rp_corporation_ranks')
            ->where('corporation_id', (int) $row->id)
            ->where('rank_order', '>', (int) ($row->rank_order ?? 0))
            ->orderBy('rank_order')
            ->first();

        $tiers = max(1, (int) ($row->tiers ?? 1));

        return new Employment(
            corporation_id: (int) $row->id,
            corporation: (string) $row->name,
            badge: (string) ($row->badge ?? ''),
            service_type: (string) ($row->service_type ?? ''),
            rank: (string) ($row->rank_name ?? __('Employee')),
            pay: (int) ($row->pay ?? 0),
            tier: min($tiers, max(1, (int) $row->tier)),
            tiers: $tiers,
            next_rank: $next !== null ? (string) $next->name : null,
            on_duty: (int) $row->on_duty === 1,
            shift_seconds: (int) $row->shift_seconds,
            hired_at: (int) $row->hired_at,
        );
    }

    /** @param  Collection<int, stdClass>  $ranks */
    private function shape(stdClass $corp, Collection $ranks, int $employees, int $onDuty): Corporation
    {
        $pays = $ranks->map(fn (stdClass $rank): int => (int) $rank->pay);

        return new Corporation(
            id: (int) $corp->id,
            name: (string) $corp->name,
            acronym: (string) ($corp->acronym ?? ''),
            description: (string) $corp->description,
            badge: (string) $corp->badge,
            service_type: (string) ($corp->service_type ?? ''),
            employees: $employees,
            on_duty: $onDuty,
            positions: $ranks->count(),
            starting_pay: $pays->isNotEmpty() ? (int) $pays->min() : 0,
            top_pay: $pays->isNotEmpty() ? (int) $pays->max() : 0,
            ranks: $ranks->map(fn (stdClass $rank): CorporationRank => new CorporationRank(
                id: (int) $rank->id,
                order: (int) $rank->rank_order,
                name: (string) $rank->name,
                pay: (int) $rank->pay,
                tiers: (int) $rank->tiers,
            ))->values(),
        );
    }
}
