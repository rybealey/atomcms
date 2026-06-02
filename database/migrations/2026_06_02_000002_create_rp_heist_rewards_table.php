<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foundation clone of create_rp_bin_rewards_table. The 'currency' reward
// type (added to bins later via a follow-up migration) is included here
// from the start.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_heist_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heist_id')->constrained('rp_heists')->cascadeOnDelete();
            $table->enum('reward_type', ['backpack_item', 'zara_ltd_token', 'currency']);
            $table->string('reward_ref', 64)
                ->comment('backpack item_key, zara_clothing_offers.id (as string), or "coins"/"diamonds"');
            $table->unsignedInteger('weight')->default(100)
                ->comment('Relative weight in the post-find-chance roll');
            $table->unsignedInteger('amount')->default(1)
                ->comment('Quantity granted on hit (forced to 1 for zara_ltd_token)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_heist_rewards');
    }
};
