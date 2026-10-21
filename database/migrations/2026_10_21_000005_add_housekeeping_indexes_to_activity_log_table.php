<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: housekeeping reads the audit trail by log and time (Staff
     * audit, Today, settings history) and by who did it (Staff); spatie's
     * table only indexes log_name, subject and causer on their own.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if (! Schema::hasIndex('activity_log', 'activity_log_log_name_created_at_index')) {
                $table->index(['log_name', 'created_at']);
            }

            if (! Schema::hasIndex('activity_log', 'activity_log_causer_id_created_at_index')) {
                $table->index(['causer_id', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['log_name', 'created_at']);
            $table->dropIndex(['causer_id', 'created_at']);
        });
    }
};
