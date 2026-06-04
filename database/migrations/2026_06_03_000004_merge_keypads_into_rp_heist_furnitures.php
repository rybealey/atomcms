<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keypads are specific PLACED furnitures (one physical keypad in a room with
// its own access code), whereas search/pickup loot is a furniture TYPE. So a
// keypad-role furniture row is now keyed by placed_item_id and carries its own
// code (next_key) + room_id, while search/pickup rows stay keyed by
// item_base_id. The separate rp_heist_keypads table is merged in and dropped.
return new class extends Migration
{
    public function up(): void
    {
        // item_base_id is only used by search/pickup rows now -> nullable.
        // (Keeps its unique index; MariaDB allows multiple NULLs there.)
        DB::statement('ALTER TABLE rp_heist_furnitures MODIFY item_base_id INT NULL');

        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->integer('placed_item_id')->nullable()->after('item_base_id')
                ->comment('items.id of the placed keypad furni (keypad role only)');
            $table->integer('room_id')->nullable()->after('placed_item_id')
                ->comment('Room of the placed keypad (keypad role; staff readability)');
            $table->unsignedTinyInteger('next_key')->nullable()->after('room_id')
                ->comment('Current 2-digit access code 0-99 (keypad role; re-rolled per open)');
            $table->unique('placed_item_id');
        });

        // Carry gameplay-created keypad codes over to placed-id keypad rows,
        // linking each placement to the heist that owned its furni base.
        if (Schema::hasTable('rp_heist_keypads')) {
            $baseToHeist = DB::table('rp_heist_furnitures')
                ->where('role', 'keypad')
                ->whereNull('placed_item_id')
                ->pluck('heist_id', 'item_base_id');

            $now = now();
            foreach (DB::table('rp_heist_keypads')->get() as $k) {
                $heistId = $baseToHeist[$k->item_base_id] ?? null;
                if ($heistId === null) {
                    continue;
                }
                DB::table('rp_heist_furnitures')->insert([
                    'heist_id' => $heistId,
                    'role' => 'keypad',
                    'placed_item_id' => $k->placed_item_id,
                    'room_id' => $k->room_id,
                    'next_key' => $k->next_key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::dropIfExists('rp_heist_keypads');
        }

        // Drop the old base-keyed keypad rows (now replaced by placed-id rows,
        // or never triggered and so re-added by placed ID going forward).
        DB::table('rp_heist_furnitures')
            ->where('role', 'keypad')
            ->whereNull('placed_item_id')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('rp_heist_keypads')) {
            Schema::create('rp_heist_keypads', function (Blueprint $table) {
                $table->id();
                $table->integer('placed_item_id')->unique();
                $table->integer('item_base_id')->default(0)->index();
                $table->integer('room_id')->index();
                $table->unsignedTinyInteger('next_key')->default(0);
                $table->timestamps();
            });
        }

        // Drop placed-id keypad rows, then restore the schema.
        DB::table('rp_heist_furnitures')->where('role', 'keypad')->whereNotNull('placed_item_id')->delete();

        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->dropUnique(['placed_item_id']);
            $table->dropColumn(['placed_item_id', 'room_id', 'next_key']);
        });

        // Remaining keypad rows have item_base_id, so NOT NULL is safe again.
        DB::table('rp_heist_furnitures')->whereNull('item_base_id')->delete();
        DB::statement('ALTER TABLE rp_heist_furnitures MODIFY item_base_id INT NOT NULL');
    }
};
