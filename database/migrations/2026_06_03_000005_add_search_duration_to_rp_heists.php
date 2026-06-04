<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stand-and-search (bin-style) duration for this heist's Search furnitures.
// rp_heists.search_seconds is the keypad access-window duration, so the
// furni-search dig time gets its own column (admin-configurable in ASE).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->integer('search_duration_seconds')->default(10)->after('search_seconds')
                ->comment('Seconds a player must stand and search a Search furniture (bin-style)');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->dropColumn('search_duration_seconds');
        });
    }
};
