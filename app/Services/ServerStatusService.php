<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Surfaces emulator runtime state to the website chrome (footer status
 * pill, header Play Now button + player count). Source of truth is the
 * emulator_settings row written by ShutdownLockdownManager when staff
 * run :shutdown / :shutdown lift.
 *
 * Cached for {@see self::CACHE_TTL_SECONDS} to bound the per-request DB
 * load — every page render hits both the header (player count + Play
 * Now gate) and the footer (status pill), and the footer polls
 * /api/deploy-status every 10s.
 */
class ServerStatusService
{
    private const CACHE_KEY = 'pixelrp.runtime.shutdown_active';

    private const CACHE_TTL_SECONDS = 5;

    private const DB_KEY = 'pixelrp.runtime.shutdown_active';

    public function isShutdown(): bool
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): bool {
                if (! Schema::hasTable('emulator_settings')) {
                    return false;
                }

                $value = DB::table('emulator_settings')
                    ->where('key', self::DB_KEY)
                    ->value('value');

                return $value === '1';
            });
        } catch (Throwable) {
            return false;
        }
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
