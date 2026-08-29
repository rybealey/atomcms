<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Console\Command;

/** Manual one-off resync for support/debugging. */
class DiscordResync extends Command
{
    protected $signature = 'discord:resync {username : In-game username to resync}';

    protected $description = 'Force a Discord nickname/role sync for one user';

    public function handle(DiscordApi $api, DiscordSyncService $sync): int
    {
        if (! $api->configured()) {
            $this->error('Discord credentials are not configured.');

            return self::FAILURE;
        }

        $user = User::query()->where('username', $this->argument('username'))->first();

        if (! $user) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        if (! $user->discord_id) {
            $this->warn('That user has no Discord account linked.');

            return self::FAILURE;
        }

        $sync->syncUser($user);
        $this->info("Synced {$user->username} (Discord id {$user->discord_id}).");

        return self::SUCCESS;
    }
}
