<?php

namespace App\Support\Housekeeping;

use App\Models\Game\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The hotel's rank ladder as the housekeeping panel shows it: one row per
 * rank with its name, badge and member count, whichever emulator driver
 * backs the ranks table (Plus: `ranks`, Arcturus: `permissions`).
 */
class RankDirectory
{
    /**
     * @return Collection<int, array{id: int, name: string, badge: string, members: int}>
     */
    public function all(): Collection
    {
        /** @var array<int, array{id: int, name: string, badge: string, members: int}> $rows */
        $rows = Cache::remember('housekeeping:ranks', now()->addMinutes(5), function (): array {
            /** @var Collection<int, int> $members */
            $members = User::query()
                ->selectRaw('`rank`, COUNT(*) AS members')
                ->groupBy('rank')
                ->pluck('members', 'rank');

            return Permission::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Permission $rank): array => [
                    'id' => (int) $rank->id,
                    'name' => $rank->rank_name,
                    'badge' => $rank->getBadgeName(),
                    'members' => (int) ($members->get((int) $rank->id) ?? 0),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    public function name(int $rank): string
    {
        $row = $this->all()->firstWhere('id', $rank);

        return $row !== null ? $row['name'] : sprintf('Rank %d', $rank);
    }

    public static function clearCache(): void
    {
        Cache::forget('housekeeping:ranks');
    }
}
