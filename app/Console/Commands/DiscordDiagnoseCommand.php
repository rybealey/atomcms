<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Discord\ConnectionMetadataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

// One-shot diagnostic: prints config, the user's stored Discord state,
// what Discord currently has for them, what we'd push, and the raw
// response. Surfaces any silent failures from sync().
class DiscordDiagnoseCommand extends Command
{
    protected $signature = 'discord:diagnose {username}';

    protected $description = 'Print Discord connection state and exercise a sync for one user';

    public function handle(ConnectionMetadataService $metadata): int
    {
        $username = $this->argument('username');
        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->error("user not found: {$username}");
            return self::FAILURE;
        }

        $this->line('--- config ---');
        $this->line('application_id: ' . (config('services.discord.application_id') ?: '(missing)'));
        $this->line('client_id:      ' . (config('services.discord.client_id') ?: '(missing)'));
        $this->line('client_secret:  ' . (config('services.discord.client_secret') ? '(set)' : '(missing)'));
        $this->line('redirect_uri:   ' . (config('services.discord.redirect_uri') ?: '(missing)'));
        $this->line('platform_name:  ' . (config('services.discord.platform_name') ?: '(missing)'));
        $this->line('bot_token:      ' . (config('services.discord.bot_token') ? '(set)' : '(missing)'));

        $this->line('');
        $this->line('--- user.' . $user->username . ' ---');
        $this->line('discord_id:                ' . ($user->discord_id ?: '(null)'));
        $this->line('discord_username:          ' . ($user->discord_username ?: '(null)'));
        $this->line('discord_verified_at:       ' . optional($user->discord_verified_at)->toDateTimeString() ?: '(null)');
        $this->line('discord_access_token:      ' . ($user->discord_access_token ? '(set, ' . strlen($user->discord_access_token) . ' chars)' : '(null)'));
        $this->line('discord_refresh_token:     ' . ($user->discord_refresh_token ? '(set)' : '(null)'));
        $this->line('discord_token_expires_at:  ' . optional($user->discord_token_expires_at)->toDateTimeString() ?: '(null)');
        $this->line('discord_scopes:            ' . ($user->discord_scopes ?: '(null)'));
        $this->line('discord_revoked_at:        ' . optional($user->discord_revoked_at)->toDateTimeString() ?: '(null)');
        $this->line('discord_metadata_synced_at:' . optional($user->discord_metadata_synced_at)->toDateTimeString() ?: '(null)');

        if (!$user->discord_access_token || $user->discord_revoked_at) {
            $this->warn('No usable OAuth token. Stop here — re-run /auth/discord/start.');
            return self::FAILURE;
        }

        $this->line('');
        $this->line('--- GET current role-connection from Discord ---');
        $appId = config('services.discord.application_id');
        $get = Http::withToken($user->discord_access_token)
            ->acceptJson()
            ->get("https://discord.com/api/v10/users/@me/applications/{$appId}/role-connection");
        $this->line('status: ' . $get->status());
        $this->line('body:   ' . $get->body());

        $this->line('');
        $this->line('--- registered metadata schema (bot-token GET) ---');
        $botToken = config('services.discord.bot_token');
        if ($botToken) {
            $schema = Http::withHeaders(['Authorization' => 'Bot ' . $botToken])
                ->get("https://discord.com/api/v10/applications/{$appId}/role-connections/metadata");
            $this->line('status: ' . $schema->status());
            $this->line('body:   ' . $schema->body());
        } else {
            $this->warn('skipped — DISCORD_BOT_TOKEN missing');
        }

        $this->line('');
        $this->line('--- attempting sync() ---');
        $ok = $metadata->sync($user);
        $this->line($ok ? 'sync: ok' : 'sync: failed (see laravel.log for HTTP details)');

        $this->line('');
        $this->line('--- GET role-connection after sync ---');
        $user->refresh();
        $get2 = Http::withToken($user->discord_access_token)
            ->acceptJson()
            ->get("https://discord.com/api/v10/users/@me/applications/{$appId}/role-connection");
        $this->line('status: ' . $get2->status());
        $this->line('body:   ' . $get2->body());

        return self::SUCCESS;
    }
}
