<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Search duration belongs to each Search furniture, not the whole heist, so
// different Search furnitures can take different times. Move
// search_duration_seconds from rp_heists onto rp_heist_furnitures (role=search
// rows), carrying existing values across.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->integer('search_duration_seconds')->nullable()->after('next_key')
                ->comment('Stand-and-search dig seconds (role=search furnitures only)');
        });

        DB::statement(
            'UPDATE rp_heist_furnitures hf JOIN rp_heists h ON h.id = hf.heist_id '
            . "SET hf.search_duration_seconds = h.search_duration_seconds WHERE hf.role = 'search'"
        );

        Schema::table('rp_heists', function (Blueprint $table) {
            $table->dropColumn('search_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->integer('search_duration_seconds')->default(10)->after('search_seconds');
        });

        DB::statement(
            'UPDATE rp_heists h JOIN rp_heist_furnitures hf ON hf.heist_id = h.id '
            . "SET h.search_duration_seconds = hf.search_duration_seconds "
            . "WHERE hf.role = 'search' AND hf.search_duration_seconds IS NOT NULL"
        );

        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->dropColumn('search_duration_seconds');
        });
    }
};
