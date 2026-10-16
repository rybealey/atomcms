<?php

namespace App\Data\Roleplay;

/** Where a character works: their corporation, rank and promotion progress. */
final class Employment
{
    public function __construct(
        public readonly int $corporation_id,
        public readonly string $corporation,
        public readonly string $badge,
        public readonly string $service_type,
        public readonly string $rank,
        public readonly int $pay,
        public readonly int $tier,
        public readonly int $tiers,
        public readonly ?string $next_rank,
        public readonly bool $on_duty,
        public readonly int $shift_seconds,
        public readonly int $hired_at,
    ) {}
}
