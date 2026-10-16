<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pixelrp: the log in history behind Settings > Sessions. One row per
     * attempt, successful or not, written by RecordLoginAttempt. Rows older
     * than 90 days are pruned by the model (MassPrunable).
     */
    public function up(): void
    {
        Schema::create('website_login_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: a failed attempt against a username that does not exist
            // still gets a row, so a guessing spree is visible.
            $table->integer('user_id')->nullable();
            $table->string('username', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->boolean('succeeded');
            $table->string('method', 32);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_login_logs');
    }
};
