<?php

namespace App\Support\Housekeeping;

use DateTimeInterface;

/**
 * One time format for the whole API. Emulator tables keep unix seconds in
 * int, double or char columns; CMS tables keep timestamps. Everything leaves
 * the API as epoch seconds (0 for "never").
 */
final class Epoch
{
    public static function of(mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if ($value === null || $value === '' || $value === '0') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $parsed = is_string($value) ? strtotime($value) : false;

        return $parsed === false ? 0 : $parsed;
    }
}
