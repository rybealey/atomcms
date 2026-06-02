<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foundation clone of create_rp_bin_searches_table (the heist audit log).
// result_type includes 'currency' from the start so currency hauls log
// cleanly (the bins searches table omitted it).
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('rp_heist_attempts');
    }
};
