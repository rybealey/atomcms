<?php

namespace App\Data\Community;

/** Where one player stands in a leaderboard category. */
final class LeaderboardStanding
{
    public function __construct(
        public readonly int $rank,
        public readonly int $value,
    ) {}
}
