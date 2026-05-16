<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user Spotify OAuth tokens for the room-102 ("The Muse") music
 * player. Keyed by users.id (= habbo id). Written by SpotifyAuthController
 * on the OAuth callback / token refresh; read + refreshed by the
 * pixeltower-rp plugin's SpotifyClient (which expects exactly these column
 * names; spotify_token_expires_at is epoch SECONDS, spotify_premium is a
 * 0/1 flag). Mirrors the Discord-columns migration.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
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
};
