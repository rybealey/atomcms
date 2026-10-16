<?php

namespace App\Services\Community;

use App\Emulator\Contracts\CurrencyRepository;
use App\Emulator\Contracts\PlayerStatsRepository;
use App\Emulator\Data\LeaderboardEntry;
use App\Emulator\Data\Stat;
use App\Enums\CurrencyTypes;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The city's top 10 in every category, and where one player stands.
 *
 * Wealth comes through the emulator drivers (credits, bank deposits,
 * diamonds); the roleplay numbers are the PixelRP tables themselves - charges
 * filed, shifts worked, gym levels - and are only offered where those tables
 * exist. Staff and the hotel's own accounts are never ranked.
 */
class LeaderboardService
{
    public const SIZE = 10;

    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly PlayerStatsRepository $stats,
        private readonly StaffService $staffService,
    ) {}

    /**
     * Every category, grouped, each with its top entries and the player's own
     * rank and value.
     *
     * @return Collection<int, object{id: string, group: string, label: string, title: string, unit: string, entries: Collection<int, LeaderboardEntry>, me: object{rank: int, value: int}}>
     */
    public function categories(User $me): Collection
    {
        $exclude = $this->excludedIds();

        $categories = collect([
            $this->currency('cash', __('Wealth'), __('Cash'), __('Most cash on hand'), CurrencyTypes::Credits, $me, $exclude, 'dollars'),
            $this->deposits($me, $exclude),
            $this->currency('diamonds', __('Wealth'), __('Diamonds'), __('Most Diamonds'), CurrencyTypes::Diamonds, $me, $exclude, ''),
            $this->arrests($me, $exclude),
            $this->shifts($me, $exclude),
            $this->gym('strength', __('Strength'), __('Strongest in the gym'), 'strength_level', $me, $exclude),
            $this->gym('stamina', __('Stamina'), __('Most stamina'), 'stamina_level', $me, $exclude),
            $this->online($me, $exclude),
        ]);

        return $categories->filter()->values();
    }

    /** @param  array<int, int>  $exclude */
    private function currency(string $id, string $group, string $label, string $title, CurrencyTypes $type, User $me, array $exclude, string $unit): object
    {
        return $this->category($id, $group, $label, $title, $unit,
            $this->currencies->topBy($type, self::SIZE, $exclude),
            $this->currencies->rankOf($me, $type, $exclude),
            $this->currencies->balance($me, $type),
        );
    }

    /** @param  array<int, int>  $exclude */
    private function deposits(User $me, array $exclude): ?object
    {
        if (! RoleplaySchema::has('rp_bank_accounts')) {
            return null;
        }

        $value = DB::raw('(current_balance + savings_balance)');

        $rows = DB::table('rp_bank_accounts')
            ->whereNotIn('user_id', $exclude)
            ->orderByDesc($value)
            ->limit(self::SIZE)
            ->get(['user_id', DB::raw('(current_balance + savings_balance) AS total')]);

        $mine = (int) (DB::table('rp_bank_accounts')->where('user_id', $me->id)->value(DB::raw('(current_balance + savings_balance)')) ?? 0);
        $rank = DB::table('rp_bank_accounts')->whereNotIn('user_id', $exclude)->where($value, '>', $mine)->count() + 1;

        return $this->category('deposits', __('Wealth'), __('Deposits'), __('Largest bank deposits'), 'dollars',
            $this->entries($rows, 'user_id', 'total'), $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function arrests(User $me, array $exclude): ?object
    {
        if (! RoleplaySchema::has('rp_charges')) {
            return null;
        }

        $rows = DB::table('rp_charges')
            ->selectRaw('officer_id, COUNT(*) AS total')
            ->whereNotIn('officer_id', $exclude)
            ->where('officer_id', '>', 0)
            ->groupBy('officer_id')
            ->orderByDesc('total')
            ->limit(self::SIZE)
            ->get();

        $mine = DB::table('rp_charges')->where('officer_id', $me->id)->count();
        $rank = DB::table('rp_charges')
            ->selectRaw('officer_id')
            ->whereNotIn('officer_id', $exclude)
            ->where('officer_id', '>', 0)
            ->groupBy('officer_id')
            ->havingRaw('COUNT(*) > ?', [$mine])
            ->get()
            ->count() + 1;

        return $this->category('arrests', __('Roleplay'), __('Arrests'), __('Most charges filed'), '',
            $this->entries($rows, 'officer_id', 'total'), $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function shifts(User $me, array $exclude): ?object
    {
        if (! RoleplaySchema::has('rp_corporation_employees')) {
            return null;
        }

        $rows = DB::table('rp_corporation_employees')
            ->whereNotIn('user_id', $exclude)
            ->orderByDesc('shift_seconds')
            ->limit(self::SIZE)
            ->get(['user_id', 'shift_seconds']);

        $mine = (int) (DB::table('rp_corporation_employees')->where('user_id', $me->id)->value('shift_seconds') ?? 0);
        $rank = DB::table('rp_corporation_employees')->whereNotIn('user_id', $exclude)->where('shift_seconds', '>', $mine)->count() + 1;

        return $this->category('shifts', __('Roleplay'), __('Shifts'), __('Most hours on shift'), 'hours',
            $this->entries($rows, 'user_id', 'shift_seconds')->map(fn (LeaderboardEntry $entry): LeaderboardEntry => new LeaderboardEntry($entry->user, intdiv($entry->value, 3600))),
            $rank, intdiv($mine, 3600));
    }

    /** @param  array<int, int>  $exclude */
    private function gym(string $id, string $label, string $title, string $column, User $me, array $exclude): ?object
    {
        if (! RoleplaySchema::hasColumn('user_rp_stats', $column)) {
            return null;
        }

        $rows = DB::table('user_rp_stats')
            ->whereNotIn('user_id', $exclude)
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->limit(self::SIZE)
            ->get(['user_id', $column]);

        $mine = (int) (DB::table('user_rp_stats')->where('user_id', $me->id)->value($column) ?? 0);
        $rank = DB::table('user_rp_stats')->whereNotIn('user_id', $exclude)->where($column, '>', $mine)->count() + 1;

        return $this->category($id, __('Roleplay'), $label, $title, 'level',
            $this->entries($rows, 'user_id', $column), $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function online(User $me, array $exclude): object
    {
        $entries = $this->stats->topBy(Stat::OnlineTime, self::SIZE, $exclude)
            ->map(fn (LeaderboardEntry $entry): LeaderboardEntry => new LeaderboardEntry($entry->user, intdiv($entry->value, 3600)));

        return $this->category('online', __('Activity'), __('Online'), __('Most hours online'), 'hours',
            $entries, $this->stats->rankOf($me, Stat::OnlineTime, $exclude), intdiv($this->stats->valueOf($me, Stat::OnlineTime), 3600));
    }

    /**
     * @param  Collection<int, LeaderboardEntry>  $entries
     */
    private function category(string $id, string $group, string $label, string $title, string $unit, Collection $entries, int $rank, int $value): object
    {
        return (object) [
            'id' => $id,
            'group' => $group,
            'label' => $label,
            'title' => $title,
            'unit' => $unit,
            'entries' => $entries->values(),
            'me' => (object) ['rank' => $rank, 'value' => $value],
        ];
    }

    /**
     * Rows of (user id, value) into entries, dropping ids that are no longer users.
     *
     * @param  Collection<int, object>  $rows
     *
     * @return Collection<int, LeaderboardEntry>
     */
    private function entries(Collection $rows, string $idColumn, string $valueColumn): Collection
    {
        $users = User::whereKey($rows->pluck($idColumn)->all())->get(['id', 'username', 'look'])->keyBy('id');

        return $rows
            ->map(function (object $row) use ($users, $idColumn, $valueColumn): ?LeaderboardEntry {
                $user = $users->get($row->{$idColumn});

                return $user === null ? null : new LeaderboardEntry($user, (int) $row->{$valueColumn});
            })
            ->filter()
            ->values();
    }

    /**
     * Staff, and the hotel's own accounts (PixelRP), are never ranked.
     *
     * @return array<int, int>
     */
    private function excludedIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->staffService->fetchEmployeeIds(),
            User::where('rp_system_account', 1)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        )));
    }
}
