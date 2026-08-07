<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        Schema::table($rankTable, function (Blueprint $table) use ($rankTable) {
            if (Schema::hasColumn($rankTable, 'can_apply')) {
                Schema::dropColumns($rankTable, 'can_apply');
            }
        });
    }

    public function down(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        if (! Schema::hasColumn($rankTable, 'can_apply')) {
            Schema::table($rankTable, function (Blueprint $table) {
                $table->boolean('can_apply')->default(false);
            });
        }
    }
};
