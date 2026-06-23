<?php

use App\Enums\FulfillmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Per-vendor fulfillment lives on the line so a multi-vendor order
            // can be partially shipped. Composite index supports the vendor's
            // "my unshipped lines" query.
            $table->string('status')
                ->default(FulfillmentStatus::Pending->value)
                ->after('line_total');

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
