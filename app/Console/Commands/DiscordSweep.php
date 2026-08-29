<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Services\Discord\DiscordSyncService;
use Illuminate\Console\Command;

/**
 * Reconciliation sweep over every linked account. The queue covers the
 * common events; this catches everything else - VIP expiring quietly, a
 * crashed emulator that never enqueued a logout, roles hand-edited in
 * Discord - so state converges within ten minutes no matter what.
 */
class DiscordSweep extends Command
{
    protected $signature = 'discord:sweep';

    protected $description = 'Reconcile Discord nicknames and roles for all linked accounts';

    public function handle(DiscordApi $api, DiscordSyncService $sync): int
    {
        if (! $api->configured()) {
            return self::SUCCESS;
        }

        $count = 0;

        User::query()
            ->whereNotNull('discord_id')
            ->chunkById(100, function ($users) use ($sync, &$count) {
                foreach ($users as $user) {
                    $sync->syncUser($user);
                    $count++;
                }
            });

        $this->info("Reconciled {$count} linked account(s).");

        return self::SUCCESS;
    }
}
