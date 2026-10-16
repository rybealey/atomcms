<?php

namespace App\Emulator\Contracts;

use App\Emulator\Data\LeaderboardEntry;
use App\Emulator\Data\Stat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads player statistics (online time, respects, achievement score) that each
 * emulator stores in its own way - Arcturus in users_settings, Plus in
 * user_stats, with different column names.
 */
interface PlayerStatsRepository
{
    /**
     * The highest-ranked players by a statistic, highest first.
     *
     * @param  array<int, int>  $excludeUserIds
     *
     * @return Collection<int, LeaderboardEntry>
     */
    public function topBy(Stat $stat, int $limit, array $excludeUserIds = []): Collection;

    /** A player's own value for a statistic. */
    public function valueOf(User $user, Stat $stat): int;

    /**
     * Where a player stands in a statistic: 1 for the top. Ties share the
     * better rank.
     *
     * @param  array<int, int>  $excludeUserIds
     */
    public function rankOf(User $user, Stat $stat, array $excludeUserIds = []): int;
}
