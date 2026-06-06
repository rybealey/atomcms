<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-heist access gating: whether passive players may take part, and whether
// the triggering player must be in a gang with a minimum number of members
// online. Defaults keep existing heists open to everyone (allow_passive on,
// gang not required).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->boolean('allow_passive_players')->default(true)->after('find_chance_pct')
                ->comment('Whether players in passive mode may take part in this heist');
            $table->boolean('gang_required')->default(false)->after('allow_passive_players')
                ->comment('Whether the triggering player must belong to a gang');
            $table->unsignedInteger('gang_min_online')->default(1)->after('gang_required')
                ->comment('Minimum gang members online required when gang_required is set');
        });
    }

    public function down(): void
    {
        Schema::table('rp_heists', function (Blueprint $table) {
            $table->dropColumn(['allow_passive_players', 'gang_required', 'gang_min_online']);
        });
    }
};
