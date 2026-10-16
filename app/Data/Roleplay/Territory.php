<?php

namespace App\Data\Roleplay;

/** A block somebody holds: the turf room and the gang that claimed it. */
final class Territory
{
    public function __construct(
        public readonly int $room_id,
        public readonly string $district,
        public readonly int $gang_id,
        public readonly string $gang,
        public readonly int $claimed_at,
    ) {}
}
