<?php

namespace App\Support\Housekeeping;

use App\Models\WebsiteDrawBadge;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The numbers the rail and the Today queue count from, and whether the
 * emulator answers. Shared by every open panel for fifteen seconds.
 */
final class Pulse
{
    /** A 911 call nobody answered within this long has gone cold. */
    public const CALL_WINDOW = 3600;

    /** Browser-guard flags from this far back still need a look. */
    public const GUARD_WINDOW = 86400;

    /**
     * @return array{counts: array<string, int>, emulator: array{online: bool}}
     */
    public static function current(): array
    {
        /** @var array{counts: array<string, int>, emulator: array{online: bool}} $pulse */
        $pulse = Cache::remember('housekeeping:pulse', now()->addSeconds(15), fn (): array => [
            'counts' => self::counts(),
            'emulator' => ['online' => Hotel::online()],
        ]);

        return $pulse;
    }

    /**
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $now = time();

        return [
            'draw_badges' => WebsiteDrawBadge::query()->where('published', 0)->count(),
            'support_waiting' => RoleplaySchema::has('rp_support_threads')
                ? DB::table('rp_support_threads')->whereIn('status', ['waiting', 'offered'])->count()
                : 0,
            'tickets_open' => RoleplaySchema::has('moderation_tickets')
                ? DB::table('moderation_tickets')->where('status', 'open')->count()
                : 0,
            'calls_open' => RoleplaySchema::has('rp_911_calls')
                ? DB::table('rp_911_calls')->where('kind', 0)->where('responder_id', 0)->where('created_at', '>=', $now - self::CALL_WINDOW)->count()
                : 0,
            'guard_flags' => RoleplaySchema::has('rp_browser_guard_log')
                ? DB::table('rp_browser_guard_log')->where('created_at', '>=', $now - self::GUARD_WINDOW)->distinct()->count('user_id')
                : 0,
            'jailed' => RoleplaySchema::has('rp_jail')
                ? DB::table('rp_jail')->where('released_at', 0)->count()
                : 0,
        ];
    }

    public static function forget(): void
    {
        Cache::forget('housekeeping:pulse');
    }
}
