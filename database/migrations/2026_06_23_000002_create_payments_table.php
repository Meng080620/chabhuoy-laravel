<?php

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // The idempotency key the gateway dedupes on. The UNIQUE index is the
            // real guarantee: a duplicate charge attempt can't create a second
            // row, so we can never double-capture even under a race.
            $table->string('idempotency_key')->unique();

            $table->string('reference')->nullable(); // gateway transaction id
            $table->string('status')->default(PaymentStatus::Succeeded->value);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
