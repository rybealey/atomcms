<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links each placed-keypad code row to its parent Heist. A Heist (rp_heists)
// is keyed by item_base_id; the emulator stamps the keypad furni's base id on
// every code row so placements nest under the matching Heist in ASE (managed
// via the HeistKeypadsRelationManager instead of a standalone page). Rows
// created before this column existed keep item_base_id = 0 and read as
// orphaned (not shown under any Heist).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heist_keypads', function (Blueprint $table) {
            $table->integer('item_base_id')->default(0)->index()->after('placed_item_id')
                ->comment('items_base.id of the keypad furni — links this placement to its parent Heist (rp_heists.item_base_id)');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heist_keypads', function (Blueprint $table) {
            $table->dropColumn('item_base_id');
        });
    }
};
