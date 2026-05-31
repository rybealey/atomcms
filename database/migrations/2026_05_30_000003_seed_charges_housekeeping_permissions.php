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
                'permission' => 'manage_charge_types',
                'min_rank' => 7,
                'description' => 'Roleplay > Charge Types: create, view, and edit chargeable crimes',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'permission' => 'delete_charge_types',
                'min_rank' => 7,
                'description' => 'Roleplay > Charge Types: delete chargeable crimes',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('website_housekeeping_permissions')
            ->whereIn('permission', ['manage_charge_types', 'delete_charge_types'])
            ->delete();
    }
};
