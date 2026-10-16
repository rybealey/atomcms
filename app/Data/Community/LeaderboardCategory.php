<?php

namespace App\Data\Community;

use App\Emulator\Data\LeaderboardEntry;
use Illuminate\Support\Collection;

/** One leaderboard: its top entries and the viewing player's own standing. */
final class LeaderboardCategory
{
    /** @param  Collection<int, LeaderboardEntry>  $entries */
    public function __construct(
        public readonly string $id,
        public readonly string $group,
        public readonly string $label,
        public readonly string $title,
        public readonly string $unit,
        public readonly Collection $entries,
        public readonly LeaderboardStanding $me,
    ) {}
}
