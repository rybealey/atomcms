<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_stripe_transactions', function (Blueprint $table) {
            $table->id();

            $table->integer('user_id');
            $table->string('checkout_session_id')->unique();
            $table->string('payment_intent_id')->nullable();
            $table->string('event_id')->nullable()->index();
            $table->string('status')->default('pending');
            $table->unsignedInteger('diamonds');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 8)->default('USD');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_stripe_transactions');
    }
};
