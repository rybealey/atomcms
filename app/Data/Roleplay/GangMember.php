<?php

namespace App\Data\Roleplay;

/** A gang member's head on the home page. */
final class GangMember
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $look,
    ) {}
}
