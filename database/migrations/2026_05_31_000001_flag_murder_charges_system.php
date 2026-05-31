<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * murder + copmurder are now applied automatically by the emulator on a
 * knockout (and copmurder is referenced by key for on-duty-cop KOs), so their
 * keys must be protected the same way 911abuse is: flag them is_system so ASE
 * blocks renaming/deleting them. Their fine / jail / enabled flags stay
 * editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rp_charge_types')
            ->whereIn('crime_key', ['murder', 'copmurder'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        DB::table('rp_charge_types')
            ->whereIn('crime_key', ['murder', 'copmurder'])
            ->update(['is_system' => false]);
    }
};
