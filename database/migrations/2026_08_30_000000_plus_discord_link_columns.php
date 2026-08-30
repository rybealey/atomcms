<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the emulator-owned Discord schema (PlusEMU's 45_DiscordLink.sql and
 * 46_DiscordUnlink.sql) into the Laravel-managed schema, so the test database
 * - which is built by migrate:fresh and never sees the emulator's SQL updates
 * - has the columns the Discord services read and write.
 *
 * Every add is guarded on hasColumn/hasTable, so this is a no-op on beta
 * and prod where the emulator's own SQL updates already applied the same
 * schema. Unlike the neighbouring plus_users_compatibility_columns
 * migration, this one is NOT gated on `emulator.driver === 'plus'`: that
 * guard exists there to patch Atom-expected columns onto PlusEMU's native
 * schema, which is the opposite problem - here the schema gap is on the
 * Arcturus side (the `testing` database tests/Feature/Discord runs
 * against), so gating on 'plus' would skip this migration in exactly the
 * config that needs it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'discord_id')) {
                $table->string('discord_id', 32)->nullable()->default(null)->unique('idx_users_discord_id');
            }

            if (! Schema::hasColumn('users', 'discord_linked_at')) {
                $table->integer('discord_linked_at')->default(0);
            }
        });

        if (! Schema::hasTable('discord_sync_queue')) {
            Schema::create('discord_sync_queue', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->string('discord_id', 32)->nullable()->default(null);
                $table->string('reason', 24)->default('');
                $table->integer('created_at')->default(0);

                $table->index('user_id', 'idx_dsq_user');
            });

            return;
        }

        // The table predates 46_DiscordUnlink.sql on this database.
        if (! Schema::hasColumn('discord_sync_queue', 'discord_id')) {
            Schema::table('discord_sync_queue', function (Blueprint $table) {
                $table->string('discord_id', 32)->nullable()->default(null)->after('user_id');
            });
        }
    }

    public function down(): void
    {
    }
};
