<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Drop the heist attempts log. It existed only to power a rolling-24h search
// cap cloned from Dumpster Diving (5 regular / 10 VIP), which was never meant
// for heists: heist access is gated by the active window, not a daily limit.
// With the cap, the :heists status command, and all emulator reads removed,
// nothing writes or reads this table anymore. down() recreates it to match the
// original create_rp_heist_attempts_table migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rp_heist_attempts');
    }

    public function down(): void
    {
        Schema::create('rp_heist_attempts', function (Blueprint $table) {
            $table->id();
            $table->integer('habbo_id')->comment('users.id of the player');
            $table->unsignedBigInteger('heist_id')->nullable()
                ->comment('rp_heists.id (nullable — keeps audit if heist deleted)');
            $table->enum('result_type', ['nothing', 'backpack_item', 'zara_ltd_token', 'currency']);
            $table->string('result_ref', 64)->nullable();
            $table->timestamp('completed_at')->useCurrent();

            $table->index(['habbo_id', 'completed_at']);
        });
    }
};
