<?php

namespace App\Data\Roleplay;

/** One employee of a corporation, as the corporation pages show them. */
final class Employee
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $look,
        public readonly bool $online,
        public readonly int $rank_id,
        public readonly int $tier,
        public readonly bool $on_duty,
        public readonly int $hired_at,
        public readonly int $shift_seconds,
        public string $rank_name = '',
    ) {}
}
