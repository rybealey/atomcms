<?php

namespace App\Data\Roleplay;

/** The numbers the city keeps on a character, as the home page shows them. */
final class PlayerStats
{
    public function __construct(
        public readonly int $online_hours,
        public readonly int $shift_hours,
        public readonly int $open_charges,
        public readonly int $jailed,
        public readonly int $health,
        public readonly int $health_max,
        public readonly int $energy,
        public readonly int $energy_max,
        public readonly int $strength,
        public readonly int $stamina,
    ) {}
}
