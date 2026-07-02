<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Snapshot of the platform's cut of this line, computed and frozen
            // at credit-on-delivery time. Null until the line is delivered — a
            // vendor's rate can change later without rewriting past lines.
            $table->decimal('commission_amount', 12, 2)->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('commission_amount');
        });
    }
};
