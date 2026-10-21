<?php

use App\Models\Game\Permission;
use App\Models\WebsiteHousekeepingPermission;
use App\Services\HousekeepingPermissionsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * pixelrp: the screens the housekeeping overhaul adds that no existing
     * capability covers. Each starts where housekeeping access itself starts,
     * so nobody gains or loses a screen on deploy - except payment data, which
     * starts at the top rank and is opened up from the Access matrix.
     */
    public function up(): void
    {
        $access = (int) (WebsiteHousekeepingPermission::query()->where('permission', 'can_access_housekeeping')->value('min_rank') ?? 6);
        // The rank table is the emulator's (Plus: ranks, Arcturus: permissions).
        $top = (int) (Permission::query()->max('id') ?? $access);

        $rows = [
            ['permission' => 'manage_support', 'min_rank' => $access, 'description' => 'The minimum rank required before being able to read the support queue and moderation tickets'],
            ['permission' => 'moderate_sitch', 'min_rank' => $access, 'description' => 'The minimum rank required before being able to open Sitch and photo moderation'],
            ['permission' => 'view_economy', 'min_rank' => $access, 'description' => 'The minimum rank required before being able to open the economy and its ledgers'],
            ['permission' => 'view_payments', 'min_rank' => max($access, $top), 'description' => 'The minimum rank required before being able to see diamond orders and other payment data'],
        ];

        foreach ($rows as $row) {
            WebsiteHousekeepingPermission::query()->firstOrCreate(['permission' => $row['permission']], $row);
        }

        HousekeepingPermissionsService::clearCache();
    }

    public function down(): void
    {
        WebsiteHousekeepingPermission::query()
            ->whereIn('permission', ['manage_support', 'moderate_sitch', 'view_economy', 'view_payments'])
            ->delete();

        HousekeepingPermissionsService::clearCache();
    }
};
