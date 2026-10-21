<?php

namespace App\Services\Community;

use App\Data\Community\LeaderboardCategory;
use App\Data\Community\LeaderboardStanding;
use App\Emulator\Contracts\CurrencyRepository;
use App\Emulator\Contracts\PlayerStatsRepository;
use App\Emulator\Data\LeaderboardEntry;
use App\Emulator\Data\Stat;
use App\Enums\CurrencyTypes;
use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The city's top 10 in every category, and where one player stands.
 *
 * Wealth comes through the emulator drivers (credits, bank deposits,
 * diamonds); the roleplay numbers are the PixelRP tables themselves - charges
 * filed, shifts worked, gym levels - and are only offered where those tables
 * exist. Staff and the hotel's own accounts are never ranked.
 *
 * pixelrp: the top 10s are the same for every viewer and were read afresh on
 * each view - about 30 queries, several of them whole-table - so they are
 * shared for a minute (top), and who is excluded for ten. Only the viewer's
 * own rank and value are read live.
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
     * @return Collection<int, LeaderboardCategory>
     */
    public function categories(User $me): Collection
    {
        $exclude = $this->excludedIds();

        $categories = [
            $this->currency('cash', __('Wealth'), __('Cash'), __('Most cash on hand'), CurrencyTypes::Credits, $me, $exclude, 'dollars'),
            $this->deposits($me, $exclude),
            $this->currency('diamonds', __('Wealth'), __('Diamonds'), __('Most Diamonds'), CurrencyTypes::Diamonds, $me, $exclude, ''),
            $this->arrests($me, $exclude),
            $this->shifts($me, $exclude),
            $this->gym('strength', __('Strength'), __('Strongest in the gym'), 'strength_level', $me, $exclude),
            $this->gym('stamina', __('Stamina'), __('Most stamina'), 'stamina_level', $me, $exclude),
            $this->online($me, $exclude),
        ];

        return (new Collection($categories))->filter()->values();
    }

    /** @param  array<int, int>  $exclude */
    private function currency(string $id, string $group, string $label, string $title, CurrencyTypes $type, User $me, array $exclude, string $unit): LeaderboardCategory
    {
        return $this->category($id, $group, $label, $title, $unit,
            $this->top($id, fn (): Collection => $this->currencies->topBy($type, self::SIZE, $exclude)),
            $this->currencies->rankOf($me, $type, $exclude),
            $this->currencies->balance($me, $type),
        );
    }

    /** @param  array<int, int>  $exclude */
    private function deposits(User $me, array $exclude): ?LeaderboardCategory
    {
        if (! RoleplaySchema::has('rp_bank_accounts')) {
            return null;
        }

        $entries = $this->top('deposits', fn (): Collection => $this->entries(DB::table('rp_bank_accounts')
            ->whereNotIn('user_id', $exclude)
            ->selectRaw('user_id, (current_balance + savings_balance) AS total')
            ->orderByRaw('(current_balance + savings_balance) DESC')
            ->limit(self::SIZE)
            ->get(), 'user_id', 'total'));

        $own = DB::table('rp_bank_accounts')
            ->where('user_id', $me->id)
            ->selectRaw('(current_balance + savings_balance) AS total')
            ->first();
        $mine = $own !== null ? (int) $own->total : 0;

        $rank = DB::table('rp_bank_accounts')
            ->whereNotIn('user_id', $exclude)
            ->whereRaw('(current_balance + savings_balance) > ?', [$mine])
            ->count() + 1;

        return $this->category('deposits', __('Wealth'), __('Deposits'), __('Largest bank deposits'), 'dollars',
            $entries, $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function arrests(User $me, array $exclude): ?LeaderboardCategory
    {
        if (! RoleplaySchema::has('rp_charges')) {
            return null;
        }

        $entries = $this->top('arrests', fn (): Collection => $this->entries(DB::table('rp_charges')
            ->selectRaw('officer_id, COUNT(*) AS total')
            ->whereNotIn('officer_id', $exclude)
            ->where('officer_id', '>', 0)
            ->groupBy('officer_id')
            ->orderByDesc('total')
            ->limit(self::SIZE)
            ->get(), 'officer_id', 'total'));

        $mine = DB::table('rp_charges')->where('officer_id', $me->id)->count();

        $ahead = DB::table('rp_charges')
            ->selectRaw('officer_id')
            ->whereNotIn('officer_id', $exclude)
            ->where('officer_id', '>', 0)
            ->groupBy('officer_id')
            ->havingRaw('COUNT(*) > ?', [$mine]);
        $rank = DB::query()->fromSub($ahead, 'ahead')->count() + 1;

        return $this->category('arrests', __('Roleplay'), __('Arrests'), __('Most charges filed'), '',
            $entries, $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function shifts(User $me, array $exclude): ?LeaderboardCategory
    {
        if (! RoleplaySchema::has('rp_corporation_employees')) {
            return null;
        }

        $entries = $this->top('shifts', fn (): Collection => $this->entries(DB::table('rp_corporation_employees')
            ->whereNotIn('user_id', $exclude)
            ->orderByDesc('shift_seconds')
            ->limit(self::SIZE)
            ->get(['user_id', 'shift_seconds']), 'user_id', 'shift_seconds')
            ->map(fn (LeaderboardEntry $entry): LeaderboardEntry => new LeaderboardEntry($entry->user, intdiv($entry->value, 3600))));

        $mine = (int) (DB::table('rp_corporation_employees')->where('user_id', $me->id)->value('shift_seconds') ?? 0);
        $rank = DB::table('rp_corporation_employees')->whereNotIn('user_id', $exclude)->where('shift_seconds', '>', $mine)->count() + 1;

        return $this->category('shifts', __('Roleplay'), __('Shifts'), __('Most hours on shift'), 'hours',
            $entries, $rank, intdiv($mine, 3600));
    }

    /** @param  array<int, int>  $exclude */
    private function gym(string $id, string $label, string $title, string $column, User $me, array $exclude): ?LeaderboardCategory
    {
        if (! RoleplaySchema::hasColumn('user_rp_stats', $column)) {
            return null;
        }

        $entries = $this->top($id, fn (): Collection => $this->entries(DB::table('user_rp_stats')
            ->whereNotIn('user_id', $exclude)
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->limit(self::SIZE)
            ->get(['user_id', $column]), 'user_id', $column));

        $mine = (int) (DB::table('user_rp_stats')->where('user_id', $me->id)->value($column) ?? 0);
        $rank = DB::table('user_rp_stats')->whereNotIn('user_id', $exclude)->where($column, '>', $mine)->count() + 1;

        return $this->category($id, __('Roleplay'), $label, $title, 'level',
            $entries, $rank, $mine);
    }

    /** @param  array<int, int>  $exclude */
    private function online(User $me, array $exclude): LeaderboardCategory
    {
        $entries = $this->top('online', fn (): Collection => $this->stats->topBy(Stat::OnlineTime, self::SIZE, $exclude)
            ->map(fn (LeaderboardEntry $entry): LeaderboardEntry => new LeaderboardEntry($entry->user, intdiv($entry->value, 3600))));

        return $this->category('online', __('Activity'), __('Online'), __('Most hours online'), 'hours',
            $entries, $this->stats->rankOf($me, Stat::OnlineTime, $exclude), intdiv($this->stats->valueOf($me, Stat::OnlineTime), 3600));
    }

    /**
     * A category's top entries, shared by every viewer for a minute. Cached as
     * plain values - the cache refuses objects (cache.serializable_classes) -
     * with just what the page shows of each player.
     *
     * @param  callable(): Collection<int, LeaderboardEntry>  $read
     *
     * @return Collection<int, LeaderboardEntry>
     */
    private function top(string $id, callable $read): Collection
    {
        $rows = Cache::remember('leaderboard:top:' . $id, now()->addMinute(), fn (): array => $read()
            ->map(fn (LeaderboardEntry $entry): array => [
                'id' => (int) $entry->user->id,
                'username' => (string) $entry->user->username,
                'look' => (string) $entry->user->look,
                'value' => $entry->value,
            ])
            ->values()
            ->all());

        return (new Collection($rows))->map(fn (array $row): LeaderboardEntry => new LeaderboardEntry(
            (new User)->setRawAttributes(['id' => $row['id'], 'username' => $row['username'], 'look' => $row['look']], true),
            $row['value'],
        ));
    }

    /**
     * @param  Collection<int, LeaderboardEntry>  $entries
     */
    private function category(string $id, string $group, string $label, string $title, string $unit, Collection $entries, int $rank, int $value): LeaderboardCategory
    {
        return new LeaderboardCategory($id, $group, $label, $title, $unit, $entries->values(), new LeaderboardStanding($rank, $value));
    }

    /**
     * Rows of (user id, value) into entries, dropping ids that are no longer users.
     *
     * @param  Collection<int, stdClass>  $rows
     *
     * @return Collection<int, LeaderboardEntry>
     */
    private function entries(Collection $rows, string $idColumn, string $valueColumn): Collection
    {
        $users = User::whereKey($rows->pluck($idColumn)->all())->get(['id', 'username', 'look'])->keyBy('id');

        return $rows
            ->map(function (stdClass $row) use ($users, $idColumn, $valueColumn): ?LeaderboardEntry {
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
        return Cache::remember('leaderboard:excluded', now()->addMinutes(10), fn (): array => array_values(array_unique(array_merge(
            $this->staffService->fetchEmployeeIds(),
            User::where('rp_system_account', 1)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        ))));
    }
}
