<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: every IP and device an account has entered the hotel from,
     * shown to staff on the housekeeping player page (App\Support\ConnectionLog).
     *
     * One row per account, IP and device - a return visit from the same place
     * bumps last_seen_at and visits instead of adding a row, so the table grows
     * with new places, not with play time. The device is a random id kept in a
     * long-lived cookie; specs are what the game page's browser reported about
     * itself (screen, graphics card, CPU cores, memory, OS version and so on).
     */
    public function up(): void
    {
        Schema::create('website_connection_logs', function (Blueprint $table) {
            $table->id();

            // The account (the root row), as the website login is; the
            // character it was playing at the time is kept alongside.
            $table->integer('user_id');
            $table->integer('character_id')->nullable();
            $table->string('ip_address', 45);
            $table->char('device_id', 32);

            // From Cloudflare: CF-IPCountry ("T1" is Tor), and city/region
            // when its visitor location headers are switched on.
            $table->string('country', 2)->nullable();
            $table->string('location', 128)->nullable();

            $table->string('user_agent', 512)->nullable();
            $table->string('accept_language', 255)->nullable();
            $table->json('specs')->nullable();

            // A short hash of the hardware the browser reported, so the same
            // machine can be matched after its cookie is cleared.
            $table->char('hardware_hash', 16)->nullable();

            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unsignedInteger('visits')->default(1);

            $table->unique(['user_id', 'ip_address', 'device_id']);
            $table->index(['user_id', 'last_seen_at']);
            $table->index('ip_address');
            $table->index('device_id');
            $table->index('hardware_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_connection_logs');
    }
};
