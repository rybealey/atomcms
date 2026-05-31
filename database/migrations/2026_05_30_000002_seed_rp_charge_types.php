<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the charge catalogue with the exact 11 crimes that used to live in the
 * plugin's hardcoded Crime enum, so day-one behaviour is identical once the
 * emulator switches to reading rp_charge_types. copmurder is non-ticketable
 * (coin_cost NULL); 911abuse is a system crime (auto-applied by dispatch code
 * and referenced by name) so it cannot be renamed/deleted in ASE.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            // crime_key,      short, display_name,    coin_cost, jail, stackable, is_system
            ['assault',        'as',   'Assault',         8,    3,  true,  false],
            ['copassault',     'cas',  'Cop Assault',     16,   6,  true,  false],
            ['robbery',        'rob',  'Robbery',         24,   9,  true,  false],
            ['trespass',       'tres', 'Trespass',        8,    3,  false, false],
            ['911abuse',       '911',  '911 Abuse',       8,    3,  false, true],
            ['drugs',          'drug', 'Drugs',           16,   6,  true,  false],
            ['murder',         'mur',  'Murder',          16,   6,  true,  false],
            ['ganghomicide',   'gh',   'Gang Homicide',   8,    3,  true,  false],
            ['copmurder',      'cm',   'Cop Murder',      null, 30, true,  false],
            ['obstruction',    'obs',  'Obstruction',     8,    3,  true,  false],
            ['terrorism',      'terr', 'Terrorism',       16,   6,  true,  false],
        ];

        $records = [];
        foreach ($rows as [$key, $short, $name, $cost, $jail, $stackable, $system]) {
            $records[] = [
                'crime_key' => $key,
                'short_key' => $short,
                'display_name' => $name,
                'coin_cost' => $cost,
                'jail_minutes' => $jail,
                'stackable' => $stackable,
                'is_system' => $system,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('rp_charge_types')->insertOrIgnore($records);
    }

    public function down(): void
    {
        DB::table('rp_charge_types')->whereIn('crime_key', [
            'assault', 'copassault', 'robbery', 'trespass', '911abuse', 'drugs',
            'murder', 'ganghomicide', 'copmurder', 'obstruction', 'terrorism',
        ])->delete();
    }
};
