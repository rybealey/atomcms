<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per PLACED keypad furni (keyed by items.id), holding that
// placement's current two-digit access code. Codes are per-placement: the
// same keypad furni dropped in five rooms gates five different codes. The
// emulator's KeypadManager upserts a fresh random code on every keypad open
// and validates submissions against it; staff can view/override codes here
// (Roleplay > Heist Keypads).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_heist_keypads', function (Blueprint $table) {
            $table->id();
            $table->integer('placed_item_id')->unique()
                ->comment('items.id of the placed keypad furni this code belongs to');
            $table->integer('room_id')->index()
                ->comment('Room the keypad is placed in — staff readability only');
            $table->unsignedTinyInteger('next_key')->default(0)
                ->comment('Current two-digit access code (0-99), re-rolled on every keypad open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_heist_keypads');
    }
};
