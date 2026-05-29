<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_bin_searches', function (Blueprint $table) {
            $table->id();
            $table->integer('habbo_id')->comment('users.id of the searcher');
            $table->unsignedBigInteger('bin_id')->nullable()
                ->comment('rp_bins.id (nullable — keeps audit if bin deleted)');
            $table->enum('result_type', ['nothing', 'backpack_item', 'zara_ltd_token']);
            $table->string('result_ref', 64)->nullable();
            $table->timestamp('completed_at')->useCurrent();

            $table->index(['habbo_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_bin_searches');
    }
};
