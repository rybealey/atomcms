<?php

namespace App\Data\Roleplay;

/** A character's balances: cash in hand, what the bank holds, diamonds. */
final class Wallet
{
    public function __construct(
        public readonly int $cash,
        public readonly int $deposits,
        public readonly int $diamonds,
        public readonly bool $has_bank,
    ) {}
}
