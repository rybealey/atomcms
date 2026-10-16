<?php

namespace App\Data\Roleplay;

/**
 * A gang in the standings. `rank` is filled once the standings are sorted;
 * `role`, `joined_at` and `total` only for the gang the player belongs to.
 */
final class Gang
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $badge,
        public readonly string $initials,
        public readonly int $owner_id,
        public readonly string $leader,
        public readonly string $leader_look,
        public readonly int $created,
        public readonly int $level,
        public readonly int $xp,
        public readonly int $members,
        public readonly int $online,
        public readonly int $turf,
        public readonly bool $recruiting,
        public int $rank = 0,
        public string $role = '',
        public int $joined_at = 0,
        public int $total = 0,
    ) {}
}
