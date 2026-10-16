<?php

namespace App\Data\Roleplay;

/** A room a corporation's ranks unlock: where you clock in. */
final class HqRoom
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
