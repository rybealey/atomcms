<?php

namespace App\Services\Roleplay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The roleplay tables are the emulator's, created by its SQL updates rather
 * than by a CMS migration, so every reader checks they exist before asking
 * for them. A hotel on another emulator simply has no corporations or gangs.
 */
final class RoleplaySchema
{
    public static function has(string $table): bool
    {
        return (bool) Cache::remember('roleplay:has-table:'.$table, now()->addMinutes(10), fn (): bool => Schema::hasTable($table));
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return (bool) Cache::remember('roleplay:has-column:'.$table.'.'.$column, now()->addMinutes(10), fn (): bool => self::has($table) && Schema::hasColumn($table, $column));
    }
}
