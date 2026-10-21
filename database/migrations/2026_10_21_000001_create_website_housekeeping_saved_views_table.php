<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: housekeeping's saved views - a named set of log explorer
     * filters ("trades over 10k", "furni deletes by staff") a staff member
     * keeps for a recurring check, private or shared with every rank that
     * can open the log explorer. `filters` holds the explorer's own query
     * (q, kind, source, player, room, when, off).
     */
    public function up(): void
    {
        Schema::create('website_housekeeping_saved_views', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('name', 64);
            $table->string('description', 255)->nullable();
            $table->json('filters');
            $table->boolean('shared')->default(false);
            // Matches today go into Today's Needs attention queue.
            $table->boolean('on_today')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index('shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_housekeeping_saved_views');
    }
};
