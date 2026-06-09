<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A "vault" (role='vault') is a heist furniture cracked by double-clicking and
// spending a lockpick. A fixed crack chance (hardcoded in the emulator's
// HeistManager, not authored here) pays a random coin amount in [min,max] on a
// hit, and the vault empties for the rest of the heist. Coins only, no treasury
// — distinct from both the Cash Box (stand-and-search) and the cash-register
// robbery (bat, treasury).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->unsignedInteger('vault_coins_min')->nullable()->after('safe_diamonds_max')
                ->comment('role=vault: minimum coins paid out on a successful crack');
            $table->unsignedInteger('vault_coins_max')->nullable()->after('vault_coins_min')
                ->comment('role=vault: maximum coins paid out on a successful crack');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heist_furnitures', function (Blueprint $table) {
            $table->dropColumn([
                'vault_coins_min',
                'vault_coins_max',
            ]);
        });
    }
};
