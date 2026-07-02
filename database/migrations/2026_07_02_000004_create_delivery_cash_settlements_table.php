<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_cash_settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('delivery_man_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            // A cash handoff is recorded the instant it happens — no async
            // provider that can fail, so unlike Payout/DeliveryEarning there's
            // no status column here.
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->index(['delivery_man_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_cash_settlements');
    }
};
