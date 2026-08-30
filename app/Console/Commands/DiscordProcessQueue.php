<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drains `discord_sync_queue` - rows the emulator enqueues on login, logout
 * and VIP redemption for linked users. Runs every minute from the scheduler;
 * each distinct user is synced once per drain no matter how many rows piled
 * up, then their rows are deleted.
 */
class DiscordProcessQueue extends Command
{
    protected $signature = 'discord:process';

    protected $description = 'Sync queued Discord role/nickname updates from the emulator';

    public function handle(DiscordApi $api, DiscordSyncService $sync): int
    {
        if (! $api->configured()) {
            return self::SUCCESS; // silently idle until credentials land
        }

        // Snapshot the horizon first: rows enqueued mid-drain survive for
        // the next run instead of being deleted un-synced.
        $maxId = (int) DB::table('discord_sync_queue')->max('id');

        if ($maxId === 0) {
            return self::SUCCESS;
        }

        $rows = DB::table('discord_sync_queue')
            ->where('id', '<=', $maxId)
            ->get();

        $synced = 0;

        // Unlink rows carry their own discord_id: the emulator already
        // cleared users.discord_id, so there is nothing left to look up.
        $unlinkIds = $rows->where('reason', 'unlink')
            ->pluck('discord_id')
            ->filter()
            ->unique();

        // Drop any id that is currently linked to somebody: the OAuth
        // callback links inline and enqueues nothing, so a player who
        // disconnects and reconnects the same Discord account (or a
        // different player who claims the freed id) inside this drain's
        // window can already be linked again by the time this row is
        // processed. Stripping roles from a currently-linked account would
        // undo the link that was just made. The row is still deleted below
        // so it never accumulates.
        $unlinkIds = $unlinkIds->diff(
            User::query()->whereIn('discord_id', $unlinkIds)->pluck('discord_id')
        );

        foreach ($unlinkIds as $discordId) {
            $sync->unlinkById((string) $discordId);
            $synced++;
        }

        $userIds = $rows->where('reason', '!=', 'unlink')
            ->pluck('user_id')
            ->unique();

        foreach (User::query()->whereIn('id', $userIds)->whereNotNull('discord_id')->get() as $user) {
            $sync->syncUser($user);
            $synced++;
        }

        DB::table('discord_sync_queue')->where('id', '<=', $maxId)->delete();

        if ($synced > 0) {
            $this->info("Synced {$synced} user(s) from the Discord queue.");
        }

        return self::SUCCESS;
    }
}
