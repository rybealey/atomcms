<?php

namespace App\Data\Roleplay;

use Illuminate\Support\Collection;

/**
 * A corporation as the website shows it. The list view fills the summary;
 * the detail view also fills who is on shift, who runs it and its HQ rooms.
 */
final class Corporation
{
    /** @var Collection<int, Employee> */
    public Collection $on_shift;

    /** @var Collection<int, HqRoom> */
    public Collection $hq_rooms;

    public ?Employee $head = null;

    public string $head_rank = '';

    /** @param  Collection<int, CorporationRank>  $ranks */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $acronym,
        public readonly string $description,
        public readonly string $badge,
        public readonly string $service_type,
        public readonly int $employees,
        public readonly int $on_duty,
        public readonly int $positions,
        public readonly int $starting_pay,
        public readonly int $top_pay,
        public Collection $ranks,
    ) {
        $this->on_shift = new Collection;
        $this->hq_rooms = new Collection;
    }
}
