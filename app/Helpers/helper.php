<?php

use App\Models\User;
use App\Services\HousekeepingPermissionsService;
use App\Services\PermissionsService;
use App\Services\SettingsService;
use App\Support\Imager;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * @template TDefault
     *
     * @param  TDefault  $default
     *
     * @return string|TDefault
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->getOrDefault($key, $default);
    }
}

if (! function_exists('hasPermission')) {
    function hasPermission(string $permission): bool
    {
        return app(PermissionsService::class)->getOrDefault($permission);
    }
}

if (! function_exists('hasHousekeepingPermission')) {
    function hasHousekeepingPermission(string $permission, ?User $user = null): bool
    {
        return app(HousekeepingPermissionsService::class)->getOrDefault($permission, user: $user);
    }
}

if (! function_exists('findMigration')) {
    function findMigration(string $tableName): string
    {
        // Iterate through all migration files in the migrations directory
        foreach (glob(database_path('migrations/*.php')) ?: [] as $filename) {
            // Check if the migration file has the Schema::create() line with the given table name
            $contents = file_get_contents($filename);

            if (is_string($contents) && str_contains($contents, "Schema::create('$tableName'")) {
                return basename($filename);
            }
        }

        // If no matching migration file is found, return an empty string
        return '';
    }
}

if (! function_exists('columnExists')) {
    function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}

if (! function_exists('dropForeignKeyIfExists')) {
    function dropForeignKeyIfExists(string $table, string $column): void
    {
        $connection = Schema::getConnection();
        $tableWithPrefix = $connection->getTablePrefix() . $table;

        $foreignKeys = collect($connection->select('SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?', [$tableWithPrefix, $column]))
            ->pluck('CONSTRAINT_NAME');

        foreach ($foreignKeys as $foreignKey) {
            if (! empty($foreignKey)) {
                $connection->statement(
                    'ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP FOREIGN KEY `' . str_replace('`', '``', $foreignKey) . '`',
                );
            }
        }
    }
}

if (! function_exists('avatar_url')) {
    /**
     * A nitro-imager render of a figure string. See App\Support\Imager.
     *
     * @param  array<string, string|int>  $params
     */
    function avatar_url(string $look, array $params = []): string
    {
        return Imager::figure($look, $params);
    }
}

if (! function_exists('players_online')) {
    /**
     * How many players are in the city right now. Shown on every page, so it
     * reads from a short cache rather than counting rows on each hit.
     */
    function players_online(): int
    {
        return (int) Cache::remember(
            'landing.players-online',
            now()->addSeconds(30),
            fn (): int => User::where('online', '1')->count(),
        );
    }
}
