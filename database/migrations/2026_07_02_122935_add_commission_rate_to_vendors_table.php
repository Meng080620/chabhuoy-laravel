<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            // Percentage of each delivered line the platform keeps, applied at
            // credit-on-delivery time. Per-vendor so a negotiated rate doesn't
            // require a schema change; admin-settable, defaults to the
            // platform's standard 10% take.
            $table->decimal('commission_rate', 5, 2)->default(10.00)->after('payout_balance');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('commission_rate');
        });
    }
};
