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
 * each run takes the oldest rows, up to BATCH of them, syncs each distinct
 * user in them once no matter how many rows piled up, then deletes those
 * rows.
 */
class DiscordProcessQueue extends Command
{
    /** Rows per run: a backlog drains over several runs, never loaded at once. */
    private const BATCH = 500;

    /** How long rows wait while Discord is not configured. */
    private const UNCONFIGURED_KEEP_SECONDS = 86400;

    protected $signature = 'discord:process';

    protected $description = 'Sync queued Discord role/nickname updates from the emulator';

    public function handle(DiscordApi $api, DiscordSyncService $sync): int
    {
        if (! $api->configured()) {
            // Idle until credentials land - but the emulator keeps queueing a
            // row per linked player's login and logout meanwhile, so rows
            // older than a day are dropped rather than left to pile up for as
            // long as Discord stays unconfigured. The last day's rows wait in
            // case it comes back; dropping older ones loses nothing, since
            // discord:sweep re-syncs every linked account anyway. Unlink rows
            // are kept: they are the only record of whose roles to strip, and
            // only an explicit disconnect makes one.
            DB::table('discord_sync_queue')
                ->where('reason', '!=', 'unlink')
                ->where('created_at', '<', time() - self::UNCONFIGURED_KEEP_SECONDS)
                ->delete();

            return self::SUCCESS;
        }

        // Oldest first. Only the rows read here are deleted below, so rows
        // enqueued mid-drain survive for the next run instead of being
        // deleted un-synced.
        $rows = DB::table('discord_sync_queue')
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

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

        DB::table('discord_sync_queue')->whereIn('id', $rows->pluck('id'))->delete();

        if ($synced > 0) {
            $this->info("Synced {$synced} user(s) from the Discord queue.");
        }

        return self::SUCCESS;
    }
}
