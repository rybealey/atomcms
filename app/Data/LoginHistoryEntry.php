<?php

namespace App\Data;

use Carbon\Carbon;

/** One line of the log in history under Settings > Sessions. */
final class LoginHistoryEntry
{
    public function __construct(
        public readonly Carbon $when,
        public readonly bool $ok,
        public readonly string $method,
        public readonly string $device,
        public readonly string $ip,
    ) {}
}
