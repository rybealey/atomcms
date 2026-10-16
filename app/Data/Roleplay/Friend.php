<?php

namespace App\Data\Roleplay;

/** A friend who is in the city right now. */
final class Friend
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $look,
        public readonly string $motto,
    ) {}
}
