<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: how many players and staff were online, every five minutes
     * (housekeeping:sample-online). The emulator only ever knows the count
     * right now; housekeeping's Today page draws the last eight days from
     * here. Pruned after 90 days by the same command.
     */
    public function up(): void
    {
        Schema::create('website_housekeeping_online_samples', function (Blueprint $table) {
            $table->timestamp('sampled_at')->primary();
            $table->unsignedInteger('online');
            $table->unsignedInteger('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_housekeeping_online_samples');
    }
};
