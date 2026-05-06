<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'discord_id')) {
                $table->string('discord_id', 20)->nullable()->unique();
            }

            if (!Schema::hasColumn('users', 'discord_username')) {
                $table->string('discord_username', 37)->nullable();
            }

            if (!Schema::hasColumn('users', 'discord_verified_at')) {
                $table->timestamp('discord_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'discord_id')) {
                $table->dropUnique(['discord_id']);
                $table->dropColumn('discord_id');
            }

            if (Schema::hasColumn('users', 'discord_username')) {
                $table->dropColumn('discord_username');
            }

            if (Schema::hasColumn('users', 'discord_verified_at')) {
                $table->dropColumn('discord_verified_at');
            }
        });
    }
};
