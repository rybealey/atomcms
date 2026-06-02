<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foundation clone of create_rp_bins_table. Column names are inherited
// verbatim from bins and can be re-themed later as heists diverge.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_heists', function (Blueprint $table) {
            $table->id();
            $table->integer('item_base_id')->unique()
                ->comment('items_base.id of the furni that acts as a heist target');
            $table->string('name', 120)
                ->comment('Staff-facing label only (e.g. "Bank Vault")');
            $table->unsignedTinyInteger('find_chance_pct')->default(70)
                ->comment('0-100 — first-roll probability of a successful haul');
            $table->integer('cooldown_seconds')->default(900)
                ->comment('Per-target global cooldown after a successful heist');
            $table->integer('search_seconds')->default(10)
                ->comment('Duration the player must stand adjacent for');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_heists');
    }
};
