<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            // The amount disbursed in this payout — a frozen historical record,
            // never recomputed from the vendor's current balance.
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('completed')->index();
            // Provider reference (bank txn id, etc.) — null until a real
            // disbursement provider is wired in; the column is ready for it.
            $table->string('reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // The vendor earnings screen reads "my payouts, newest first".
            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
