<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        if (! Schema::hasColumn($rankTable, 'hidden_rank')) {
            Schema::table($rankTable, function (Blueprint $table) use ($rankTable) {
                if (Schema::hasColumn($rankTable, 'rank_name')) {
                    $table->boolean('hidden_rank')->after('rank_name')->default(false);
                } else {
                    $table->boolean('hidden_rank')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        Schema::table($rankTable, function (Blueprint $table) {
            $table->dropColumn('hidden_rank');
        });
    }
};
