<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_men', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vehicle_type');
            $table->string('status')->default('pending')->index();
            // Platform owes the rider this much (earned delivery fees, not yet paid out).
            $table->decimal('wallet_balance', 12, 2)->default(0);
            // Rider owes the platform this much (COD cash collected, not yet settled).
            $table->decimal('cash_in_hand', 12, 2)->default(0);
            $table->boolean('is_online')->default(false);
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_men');
    }
};
