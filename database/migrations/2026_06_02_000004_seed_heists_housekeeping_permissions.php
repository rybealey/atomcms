<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Foundation clone of seed_bins_housekeeping_permissions.
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('website_housekeeping_permissions')->insertOrIgnore([
            [
                'permission' => 'manage_heists',
                'min_rank' => 7,
                'description' => 'Roleplay > Heists: create, view, and edit heists',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'permission' => 'delete_heists',
                'min_rank' => 8,
                'description' => 'Roleplay > Heists: delete heists',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('website_housekeeping_permissions')
            ->whereIn('permission', ['manage_heists', 'delete_heists'])
            ->delete();
    }
};
