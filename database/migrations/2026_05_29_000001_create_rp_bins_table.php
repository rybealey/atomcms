<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_bins', function (Blueprint $table) {
            $table->id();
            $table->integer('item_base_id')->unique()
                ->comment('items_base.id of the furni that acts as a bin');
            $table->string('name', 120)
                ->comment('Staff-facing label only (e.g. "Alley Dumpster")');
            $table->unsignedTinyInteger('find_chance_pct')->default(70)
                ->comment('0-100 — first-roll probability of finding something');
            $table->integer('cooldown_seconds')->default(900)
                ->comment('Per-bin global cooldown after a successful search');
            $table->integer('search_seconds')->default(10)
                ->comment('Search duration the player must stand adjacent for');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_bins');
    }
};
