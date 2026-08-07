<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        if (! Schema::hasColumn($rankTable, 'staff_background')) {
            Schema::table($rankTable, function (Blueprint $table) use ($rankTable) {
                if (Schema::hasColumn($rankTable, 'staff_color')) {
                    $table->string('staff_background')->default('staff-bg.png')->after('staff_color');
                } else {
                    $table->string('staff_background')->default('staff-bg.png');
                }
            });
        }
    }

    public function down(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        Schema::table($rankTable, function (Blueprint $table) {
            $table->dropColumn('staff_background');
        });
    }
};
