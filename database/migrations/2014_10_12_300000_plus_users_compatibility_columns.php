<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('emulator.driver') !== 'plus') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'real_name' => fn () => $table->string('real_name')->nullable(),
                'mail_verified' => fn () => $table->boolean('mail_verified')->default(false),
                'account_day_of_birth' => fn () => $table->integer('account_day_of_birth')->default(0),
                'last_login' => fn () => $table->integer('last_login')->default(0),
                'ip_register' => fn () => $table->string('ip_register', 45)->default(''),
                'ip_current' => fn () => $table->string('ip_current', 45)->default(''),
                'extra_rank' => fn () => $table->unsignedInteger('extra_rank')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('users', $column)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
    }
};
