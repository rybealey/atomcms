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
            'PIXELRP-BETA-PQFXGXHG',
            'PIXELRP-BETA-SWVGFT0I',
            'PIXELRP-BETA-T2P15KZB',
            'PIXELRP-BETA-93XPZO3H',
            'PIXELRP-BETA-88PVLX8Y',
            'PIXELRP-BETA-0GX3AVCI',
            'PIXELRP-BETA-T1AMS72C',
            'PIXELRP-BETA-7DWFIE21',
            'PIXELRP-BETA-T7VNUM1E',
            'PIXELRP-BETA-OL8RO1X6',
            'PIXELRP-BETA-WB0Y5X9S',
            'PIXELRP-BETA-FZ9FAIVK',
            'PIXELRP-BETA-XHZITODT',
            'PIXELRP-BETA-6S29TBOV',
            'PIXELRP-BETA-4IZVURS0',
            'PIXELRP-BETA-HGEAAPMN',
            'PIXELRP-BETA-RAHEITMH',
            'PIXELRP-BETA-D5FSG7W6',
            'PIXELRP-BETA-DG9AUFMO',
            'PIXELRP-BETA-11IPGAQI',
            'PIXELRP-BETA-LGICJ308',
            'PIXELRP-BETA-ODD05R7Y',
            'PIXELRP-BETA-MDUBP52W',
            'PIXELRP-BETA-LRNNVKBZ',
            'PIXELRP-BETA-O1VN2OWM',
            'PIXELRP-BETA-KH50YZWT',
            'PIXELRP-BETA-V6LHQGP2',
            'PIXELRP-BETA-9XCPEU6U',
            'PIXELRP-BETA-Y83OE7QO',
            'PIXELRP-BETA-2H07ZJ3O',
            'PIXELRP-BETA-3CG2PV22',
            'PIXELRP-BETA-FC8RXY2U',
            'PIXELRP-BETA-XADT02T9',
            'PIXELRP-BETA-BI3Z0MGM',
            'PIXELRP-BETA-8CYHN77E',
            'PIXELRP-BETA-3BIGC6NG',
            'PIXELRP-BETA-KS5LJK4X',
            'PIXELRP-BETA-82KNQ6EA',
            'PIXELRP-BETA-HVJCRWNW',
            'PIXELRP-BETA-J282BQIB',
            'PIXELRP-BETA-SI1O9UWF',
            'PIXELRP-BETA-7NMBXQT2',
            'PIXELRP-BETA-FI8X8K20',
            'PIXELRP-BETA-Q8Q7PZRY',
            'PIXELRP-BETA-XTF0VMQC',
            'PIXELRP-BETA-ASZ980FW',
            'PIXELRP-BETA-QHUO25GF',
            'PIXELRP-BETA-V9S19YCP',
            'PIXELRP-BETA-K2LDHHIB',
            'PIXELRP-BETA-KT9HQ9Q5',
        ];
    }
}
