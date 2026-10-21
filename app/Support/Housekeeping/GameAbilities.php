<?php

namespace App\Support\Housekeeping;

use App\Models\User;
use App\Services\Roleplay\RoleplaySchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The emulator's own permission system: permissions_commands, where
 * `group_id` is the lowest rank that may use a command. A web action that
 * mirrors an in-game ability checks the same row, so a rank that can do
 * something in the game can do it on the web, and nothing more.
 */
final class GameAbilities
{
    /**
     * The in-game abilities the panel mirrors, with what each one unlocks on
     * the web. Their thresholds are edited from the Access matrix.
     */
    public const MIRRORED = [
        'command_alert_user' => 'Alert a player',
        'command_disconnect' => 'Disconnect a player',
        'command_ban' => 'Ban a player',
        'command_mute' => 'Mute a player',
        'command_give_badge' => 'Give a badge',
        'command_update_filter' => 'Reload the word filter',
        'command_update_catalog' => 'Reload the catalog',
        'command_update_bans' => 'Reload bans',
        'rp_city_panel' => 'Open the City Panel',
        'rp_city_justice' => 'Justice: crimes, charges and jail',
        'rp_city_balance' => 'Move coins and balances',
        'rp_city_economy' => 'Economy: prices and bin rewards',
        'rp_city_world' => 'World: weather, time, maintenance, combat',
        'rp_city_shifts' => 'Shifts',
        'rp_city_uniforms' => 'Uniforms',
        'rp_furni_function' => 'Furni functions',
        'rp_sitch_moderate' => 'Sitch moderation',
    ];

    /**
     * @return array<string, int> command => lowest rank
     */
    public static function thresholds(): array
    {
        if (! RoleplaySchema::has('permissions_commands')) {
            return [];
        }

        /** @var array<string, int> $rows */
        $rows = Cache::remember('housekeeping:game-abilities', now()->addMinutes(5), fn (): array => DB::table('permissions_commands')
            ->pluck('group_id', 'command')
            ->map(fn (mixed $rank): int => (int) $rank)
            ->all());

        return $rows;
    }

    public static function allows(User $user, string $ability): bool
    {
        $threshold = self::thresholds()[$ability] ?? null;

        return $threshold !== null && (int) $user->rank >= $threshold;
    }

    /**
     * @return array<string, bool>
     */
    public static function forUser(User $user): array
    {
        $out = [];

        foreach (array_keys(self::MIRRORED) as $ability) {
            $out[$ability] = self::allows($user, $ability);
        }

        return $out;
    }

    public static function clearCache(): void
    {
        Cache::forget('housekeeping:game-abilities');
    }
}
