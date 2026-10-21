<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * pixelrp: write down how many players and staff are online, for the
 * housekeeping Today page's online sparkline and peak chart. Runs every five
 * minutes; keeps 90 days.
 */
class SampleOnlineCount extends Command
{
    protected $signature = 'housekeeping:sample-online';

    protected $description = 'Record the online player and staff count for housekeeping';

    public function handle(): int
    {
        $staffRank = (int) setting('min_staff_rank', 4);

        DB::table('website_housekeeping_online_samples')->insertOrIgnore([
            'sampled_at' => now()->startOfMinute(),
            'online' => User::query()->where('online', '1')->count(),
            'staff' => User::query()->where('online', '1')->where('rank', '>=', $staffRank)->count(),
        ]);

        DB::table('website_housekeeping_online_samples')->where('sampled_at', '<', now()->subDays(90))->delete();

        return self::SUCCESS;
    }
}
