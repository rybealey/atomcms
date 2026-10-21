<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: what staff decided about two accounts sharing an IP or a
     * device - a household (siblings, a shared PC) or dismissed - so Player
     * 360's Security tab stops flagging the pair. One row per direction.
     */
    public function up(): void
    {
        Schema::create('website_housekeeping_link_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('account_id');
            $table->integer('other_account_id');
            $table->string('status', 16);
            $table->integer('reviewed_by');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'other_account_id'], 'hk_link_reviews_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_housekeeping_link_reviews');
    }
};
