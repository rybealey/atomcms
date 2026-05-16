<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the per-user Spotify OAuth columns added by
 * 2026_05_16_000001. The music player moved to Option B (server-side
 * Spotify search + YouTube playback, no listener auth), so these columns
 * are dead. Idempotent: a no-op on any environment where the add
 * migration never ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'spotify_access_token',
                'spotify_refresh_token',
                'spotify_token_expires_at',
                'spotify_premium',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'spotify_access_token')) {
                $table->text('spotify_access_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'spotify_refresh_token')) {
                $table->text('spotify_refresh_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'spotify_token_expires_at')) {
                $table->bigInteger('spotify_token_expires_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'spotify_premium')) {
                $table->boolean('spotify_premium')->default(false);
            }
        });
    }
};
