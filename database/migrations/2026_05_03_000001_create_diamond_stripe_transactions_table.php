<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diamond_stripe_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('checkout_session_id')->unique();
            $table->string('payment_intent_id')->nullable()->index();
            $table->unsignedInteger('amount_diamonds');
            $table->unsignedInteger('amount_usd_cents');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diamond_stripe_transactions');
    }
};
