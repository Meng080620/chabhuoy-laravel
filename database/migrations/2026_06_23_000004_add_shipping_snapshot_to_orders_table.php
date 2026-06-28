<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Immutable snapshot of the destination AT PURCHASE TIME. Mirrors the
            // product_name/unit_price snapshot on order_items: a later edit to the
            // customer's saved address must never rewrite where a placed order
            // shipped. Nullable so pre-existing rows remain valid.
            $table->string('ship_recipient_name')->nullable()->after('total');
            $table->string('ship_phone', 32)->nullable()->after('ship_recipient_name');
            $table->string('ship_line1')->nullable()->after('ship_phone');
            $table->string('ship_line2')->nullable()->after('ship_line1');
            $table->string('ship_city', 120)->nullable()->after('ship_line2');
            $table->string('ship_postal_code', 20)->nullable()->after('ship_city');
            $table->string('ship_country', 2)->nullable()->after('ship_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'ship_recipient_name', 'ship_phone', 'ship_line1', 'ship_line2',
                'ship_city', 'ship_postal_code', 'ship_country',
            ]);
        });
    }
};
