<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: staff notes on a player, kept by housekeeping's Discipline tab.
     * Discipline belongs to the account, not a character, so a note is keyed
     * by the account root (users.parent_id, or the id itself) and every
     * character on it shows the same notes. Never shown to the player.
     */
    public function up(): void
    {
        Schema::create('website_staff_notes', function (Blueprint $table) {
            $table->id();
            $table->integer('account_id');
            $table->integer('character_id')->nullable();
            $table->integer('author_id');
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_staff_notes');
    }
};
