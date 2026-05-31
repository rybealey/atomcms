<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Logout" charge: applied automatically by the emulator when a player
 * disconnects while cuffed (fleeing custody). Non-ticketable, 30 minutes of
 * (display-only) jail time, and a system charge — its key is referenced in
 * plugin code, so ASE blocks renaming/deleting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rp_charge_types')->insertOrIgnore([
            'crime_key' => 'logout',
            'short_key' => 'lo',
            'display_name' => 'Logout',
            'coin_cost' => null,
            'jail_minutes' => 30,
            'stackable' => false,
            'is_system' => true,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rp_charge_types')->where('crime_key', 'logout')->delete();
    }
};
