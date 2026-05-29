<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('website_housekeeping_permissions')->insertOrIgnore([
            [
                'permission' => 'manage_bins',
                'min_rank' => 7,
                'description' => 'Roleplay > Bins: create, view, and edit dumpster diving bins',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'permission' => 'delete_bins',
                'min_rank' => 8,
                'description' => 'Roleplay > Bins: delete dumpster diving bins',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('website_housekeeping_permissions')
            ->whereIn('permission', ['manage_bins', 'delete_bins'])
            ->delete();
    }
};
