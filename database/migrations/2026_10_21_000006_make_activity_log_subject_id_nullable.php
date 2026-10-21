<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: 2024_12_31 turned activity_log.subject_id into a string and,
     * by leaving off nullable(), into NOT NULL - spatie ships it nullable.
     * Housekeeping audits changes that have no model behind them (a crime, a
     * setting, a command threshold), and those rows have no subject.
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('subject_id')->nullable(false)->change();
        });
    }
};
