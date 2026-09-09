<?php

namespace App\Support\Housekeeping;

use App\Emulator\Emulator;

/**
 * The users-table columns whose names differ between emulator drivers. Plus
 * keeps ip_last / ip_reg and has no last_login; Arcturus keeps ip_current /
 * ip_register. Everything the panel reads goes through here so a driver
 * difference is a one-line change.
 */
class PlayerColumns
{
    public static function isPlus(): bool
    {
        return Emulator::driver() === 'plus';
    }

    public static function currentIp(): string
    {
        return self::isPlus() ? 'ip_last' : 'ip_current';
    }

    public static function registerIp(): string
    {
        return self::isPlus() ? 'ip_reg' : 'ip_register';
    }

    /**
     * Columns the player list and player card select. Kept explicit so a
     * column one driver lacks is never referenced.
     *
     * @return list<string>
     */
    public static function listColumns(): array
    {
        return ['id', 'username', 'motto', 'look', 'rank', 'mail', 'account_created', 'last_online', 'online', self::currentIp()];
    }
}
