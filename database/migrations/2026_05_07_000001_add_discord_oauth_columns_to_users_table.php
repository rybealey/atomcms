<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'discord_access_token')) {
                $table->text('discord_access_token')->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_refresh_token')) {
                $table->text('discord_refresh_token')->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_token_expires_at')) {
                $table->timestamp('discord_token_expires_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_scopes')) {
                $table->string('discord_scopes', 191)->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_revoked_at')) {
                $table->timestamp('discord_revoked_at')->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_metadata_synced_at')) {
                $table->timestamp('discord_metadata_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'discord_access_token',
                'discord_refresh_token',
                'discord_token_expires_at',
                'discord_scopes',
                'discord_revoked_at',
                'discord_metadata_synced_at',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
