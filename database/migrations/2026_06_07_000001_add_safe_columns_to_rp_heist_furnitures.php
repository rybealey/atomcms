<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A "safe" is a Search-style furniture (role='safe') that pays out currency only,
// with its own per-furniture probability and amounts instead of the heist's shared
// rp_heist_rewards table. One overall award chance, then a weighted coins-vs-diamonds
// split, each currency rolling a random amount in its [min,max] range. Reuses the
// existing search_duration_seconds column for the stand-and-search dig time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->unsignedTinyInteger('safe_award_chance_pct')->nullable()->after('search_duration_seconds')
                ->comment('role=safe: chance (0-100) the safe pays out anything');
            $table->unsignedInteger('safe_coins_weight')->nullable()->after('safe_award_chance_pct')
                ->comment('role=safe: weight of the coins branch in the payout split');
            $table->unsignedInteger('safe_coins_min')->nullable()->after('safe_coins_weight')
                ->comment('role=safe: minimum coins paid out');
            $table->unsignedInteger('safe_coins_max')->nullable()->after('safe_coins_min')
                ->comment('role=safe: maximum coins paid out');
            $table->unsignedInteger('safe_diamonds_weight')->nullable()->after('safe_coins_max')
                ->comment('role=safe: weight of the diamonds branch in the payout split');
            $table->unsignedInteger('safe_diamonds_min')->nullable()->after('safe_diamonds_weight')
                ->comment('role=safe: minimum diamonds paid out');
            $table->unsignedInteger('safe_diamonds_max')->nullable()->after('safe_diamonds_min')
                ->comment('role=safe: maximum diamonds paid out');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->dropColumn([
                'safe_award_chance_pct',
                'safe_coins_weight',
                'safe_coins_min',
                'safe_coins_max',
                'safe_diamonds_weight',
                'safe_diamonds_min',
                'safe_diamonds_max',
            ]);
        });
    }
};
