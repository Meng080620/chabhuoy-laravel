<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            // A multi-vendor order ships as separate parcels — one shipment per
            // vendor per order. Carrier is an optional label; tracking_number is
            // the anchor a customer follows.
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at');
            $table->timestamps();

            // One parcel per vendor on a given order — enforces the upsert grain.
            $table->unique(['order_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
