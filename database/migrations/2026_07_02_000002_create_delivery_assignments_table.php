<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            // Set only when the vendor also typed a tracking number — a parcel
            // can be assignable to a rider without one, so this stays nullable
            // rather than being the key this table is grained on.
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_man_id')->nullable()->constrained('delivery_men')->nullOnDelete();
            $table->string('status')->default('available')->index();
            // Snapshotted at creation time so later config changes never alter
            // an in-flight assignment's payout.
            $table->decimal('delivery_fee', 10, 2);
            $table->decimal('cod_amount', 10, 2)->default(0);
            $table->string('otp', 6)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // One assignment per vendor parcel — same grain as shipments.
            $table->unique(['order_id', 'vendor_id']);
            // The rider's "current orders" list filters by both columns.
            $table->index(['delivery_man_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
