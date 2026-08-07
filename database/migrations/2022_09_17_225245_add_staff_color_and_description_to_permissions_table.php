<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        if (! Schema::hasColumn($rankTable, 'job_description')) {
            Schema::table($rankTable, function (Blueprint $table) use ($rankTable) {
                if (Schema::hasColumn($rankTable, 'badge')) {
                    $table->string('job_description')->default('Here to help')->after('badge');
                } else {
                    $table->string('job_description')->default('Here to help');
                }
            });
        }

        if (! Schema::hasColumn($rankTable, 'staff_color')) {
            Schema::table($rankTable, function (Blueprint $table) {
                $table->string('staff_color', 8)->default('#327fa8')->after('job_description');
            });
        }
    }

    public function down(): void
    {
        $rankTable = config('emulator.driver') === 'plus' ? 'ranks' : 'permissions';

        Schema::table($rankTable, function (Blueprint $table) {
            $table->dropColumn(['job_description', 'staff_color']);
        });
    }
};
