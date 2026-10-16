<?php

namespace App\Data\Roleplay;

use Illuminate\Support\Collection;

/** A position in a corporation, highest rank order first on the pages. */
final class CorporationRank
{
    /** @param  Collection<int, Employee>  $employees */
    public function __construct(
        public readonly int $id,
        public readonly int $order,
        public readonly string $name,
        public readonly int $pay,
        public readonly int $tiers,
        public Collection $employees = new Collection,
        public int $count = 0,
    ) {}
}
