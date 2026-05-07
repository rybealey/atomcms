<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

// One-shot: registers the application's role-connection metadata schema
// with Discord. Run after Discord application is created and DISCORD_*
// env vars are set; re-run whenever the metadata fields below change.
//
// Discord docs:
//   PUT /applications/{APP_ID}/role-connections/metadata
//   Auth: Bot {DISCORD_BOT_TOKEN}
//
// Metadata field types:
//   1 INTEGER_LESS_THAN_OR_EQUAL
//   2 INTEGER_GREATER_THAN_OR_EQUAL
//   3 INTEGER_EQUAL
//   4 INTEGER_NOT_EQUAL
//   5 DATETIME_LESS_THAN_OR_EQUAL
//   6 DATETIME_GREATER_THAN_OR_EQUAL
//   7 BOOLEAN_EQUAL
//   8 BOOLEAN_NOT_EQUAL
class DiscordRegisterMetadataSchemaCommand extends Command
{
    protected $signature = 'discord:register-metadata-schema';

    protected $description = 'Register the application connection metadata schema with Discord (one-shot)';

    public function handle(): int
    {
        $applicationId = config('services.discord.application_id');
        $botToken = config('services.discord.bot_token');

        if (!$applicationId || !$botToken) {
            $this->error('DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN must be set.');
            return self::FAILURE;
        }

        // Discord caps role-connection metadata at 5 records. We use 4
        // and leave room for one project-specific add (e.g. corp_rank).
        $schema = [
            [
                'key' => 'online',
                'type' => 7, // BOOLEAN_EQUAL
                'name' => 'Currently Online',
                'description' => 'Whether the player is online in Pixeltower right now',
            ],
            [
                'key' => 'verified_since',
                'type' => 6, // DATETIME_GREATER_THAN_OR_EQUAL
                'name' => 'Verified Habbo Since',
                'description' => 'Date the Discord account was first linked to a Habbo',
            ],
            [
                'key' => 'hours_played',
                'type' => 2, // INTEGER_GREATER_THAN_OR_EQUAL
                'name' => 'Hours Played',
                'description' => 'Approximate Pixeltower play time',
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $botToken,
            'Content-Type' => 'application/json',
        ])->put(
            "https://discord.com/api/v10/applications/{$applicationId}/role-connections/metadata",
            $schema
        );

        if (!$response->successful()) {
            $this->error('Discord rejected the schema:');
            $this->error($response->body());
            return self::FAILURE;
        }

        $this->info('Schema registered:');
        $this->line($response->body());
        return self::SUCCESS;
    }
}
