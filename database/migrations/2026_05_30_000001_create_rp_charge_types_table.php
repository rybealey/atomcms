<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('crime_key', 32)->unique()
                ->comment('Stable key persisted in rp_charges + matched by the plugin (e.g. "robbery")');
            $table->string('short_key', 16)->unique()
                ->comment(':charge shorthand a cop can type (e.g. "rob")');
            $table->string('display_name', 64)
                ->comment('Player-facing name (e.g. "Robbery")');
            $table->integer('coin_cost')->nullable()
                ->comment('Ticket fine per instance; NULL = non-ticketable (blocks the whole ticket)');
            $table->integer('jail_minutes')->default(0)
                ->comment('Display-only jail time fed to the Wanted List readout');
            $table->boolean('stackable')->default(true)
                ->comment('false = capped at one active charge per player');
            $table->boolean('is_system')->default(false)
                ->comment('Plugin code depends on this crime_key; ASE blocks renaming/deleting it');
            $table->boolean('enabled')->default(true)
                ->comment('Disabled rows are ignored by the emulator (WHERE enabled = 1)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_charge_types');
    }
};
