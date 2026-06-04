<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A heist owns multiple furnitures, each with a role: keypad (the access
// gate), search (stand-and-search loot), or pickup (grab-and-go loot). This
// replaces the single rp_heists.item_base_id field. item_base_id is unique
// across the table, so a furni base belongs to exactly one heist.
//
// Existing heists each had one furniture, which in practice was the keypad
// gate, so their item_base_id is migrated in as role = 'keypad' before the
// column is dropped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_heist_furnitures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('heist_id');
            $table->integer('item_base_id')->unique()
                ->comment('items_base.id of a furni attached to this heist (one heist per base)');
            $table->string('role', 16)->default('search')
                ->comment("Furniture role: 'keypad' (access gate), 'search', or 'pickup'");
            $table->timestamps();

            $table->foreign('heist_id')->references('id')->on('rp_heists')->cascadeOnDelete();
            $table->index('heist_id');
        });

        // Carry existing single-furniture heists over as keypad-role rows.
        // Plain INSERT ... SELECT (no CTE) for the deployed MariaDB build.
        DB::statement(
            'INSERT INTO rp_heist_furnitures (heist_id, item_base_id, role, created_at, updated_at) '
            . "SELECT id, item_base_id, 'keypad', NOW(), NOW() FROM rp_heists "
            . 'WHERE item_base_id IS NOT NULL'
        );

        Schema::table('rp_heists', function (Blueprint $table) {
            $table->dropUnique(['item_base_id']);
            $table->dropColumn('item_base_id');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->integer('item_base_id')->nullable()->unique()->after('id');
        });

        // Restore one furniture per heist (prefer the keypad row) before
        // dropping the child table.
        DB::statement(
            'UPDATE rp_heists h '
            . 'JOIN rp_heist_furnitures f ON f.heist_id = h.id '
            . 'SET h.item_base_id = f.item_base_id'
        );

        Schema::dropIfExists('rp_heist_furnitures');
    }
};
