<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE rp_bin_rewards MODIFY COLUMN reward_type "
            . "ENUM('backpack_item', 'zara_ltd_token', 'currency') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE rp_bin_rewards MODIFY COLUMN reward_type "
            . "ENUM('backpack_item', 'zara_ltd_token') NOT NULL"
        );
    }
};
