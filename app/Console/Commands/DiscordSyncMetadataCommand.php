<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Discord\ConnectionMetadataService;
use Illuminate\Console\Command;

// Re-pushes Discord role-connection metadata for every linked user that
// has an active OAuth grant. Runs every 10 min on the schedule so motto
// edits (which we don't get a direct event for) propagate within ~10 min.
//
// This is bulk-but-idempotent — pushing identical metadata is a no-op
// on Discord's side. Cost is one HTTPS PUT per user; with refresh-token
// caching this is a few ms per user.
class DiscordSyncMetadataCommand extends Command
{
    protected $signature = 'discord:sync-metadata
                            {--only-online : Limit to users currently in-game}
                            {--username= : Sync a single user by username (debug)}';

    protected $description = 'Re-push Discord role-connection metadata for linked users';

    public function handle(ConnectionMetadataService $metadata): int
    {
        $query = User::query()
            ->whereNotNull('discord_id')
            ->whereNotNull('discord_access_token')
            ->whereNull('discord_revoked_at');

        if ($username = $this->option('username')) {
            $query->where('username', $username);
        } elseif ($this->option('only-online')) {
            $query->where('online', true);
        }

        $count = 0;
        $failed = 0;
        $query->chunkById(100, function ($users) use ($metadata, &$count, &$failed) {
            foreach ($users as $user) {
                if ($metadata->sync($user)) {
                    $count++;
                } else {
                    $failed++;
                }
            }
        });

        $this->info("discord:sync-metadata — synced {$count}, failed {$failed}");
        return self::SUCCESS;
    }
}
