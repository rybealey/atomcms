<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the initial 50 PixelRP closed-beta access codes.
 *
 * Run once per environment: `php artisan db:seed --class=BetaCodeSeeder`.
 * Idempotent — uses insertOrIgnore so re-running won't duplicate or wipe
 * codes that have already been claimed.
 */
class BetaCodeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = array_map(
            fn (string $code) => [
                'code' => $code,
                'user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $this->codes(),
        );

        DB::table('website_beta_codes')->insertOrIgnore($rows);
    }

    /**
     * @return list<string>
     */
    private function codes(): array
    {
        return [
            'PXLRP-BETA-W9564',
            'PXLRP-BETA-PXDWV',
            'PXLRP-BETA-9VSQV',
            'PXLRP-BETA-9GJ62',
            'PXLRP-BETA-9MS3V',
            'PXLRP-BETA-M6PQH',
            'PXLRP-BETA-7DAYG',
            'PXLRP-BETA-QK6JE',
            'PXLRP-BETA-2PDNA',
            'PXLRP-BETA-23W7Y',
            'PXLRP-BETA-PC5RP',
            'PXLRP-BETA-H734B',
            'PXLRP-BETA-MRH8M',
            'PXLRP-BETA-84XQH',
            'PXLRP-BETA-2HUGG',
            'PXLRP-BETA-FEU2J',
            'PXLRP-BETA-D35YW',
            'PXLRP-BETA-DE8HE',
            'PXLRP-BETA-M88TB',
            'PXLRP-BETA-VXARH',
            'PXLRP-BETA-U5PVR',
            'PXLRP-BETA-Y4ANJ',
            'PXLRP-BETA-2GVNY',
            'PXLRP-BETA-CVGGM',
            'PXLRP-BETA-U4VQW',
            'PXLRP-BETA-4B5RK',
            'PXLRP-BETA-FA3W2',
            'PXLRP-BETA-32GVJ',
            'PXLRP-BETA-YYPKU',
            'PXLRP-BETA-ZDSMM',
            'PXLRP-BETA-9Q4X6',
            'PXLRP-BETA-9UTWG',
            'PXLRP-BETA-RRHN9',
            'PXLRP-BETA-KCNXM',
            'PXLRP-BETA-FT9RP',
            'PXLRP-BETA-24Y74',
            'PXLRP-BETA-DNP89',
            'PXLRP-BETA-WJFTJ',
            'PXLRP-BETA-8ZA3C',
            'PXLRP-BETA-WU2GT',
            'PXLRP-BETA-Y6FEE',
            'PXLRP-BETA-Z2JH7',
            'PXLRP-BETA-9FP9T',
            'PXLRP-BETA-SJ8Y7',
            'PXLRP-BETA-QSE94',
            'PXLRP-BETA-YD5SW',
            'PXLRP-BETA-NCTWS',
            'PXLRP-BETA-KGBP3',
            'PXLRP-BETA-UUANY',
            'PXLRP-BETA-VVBY7',
        ];
    }
}
