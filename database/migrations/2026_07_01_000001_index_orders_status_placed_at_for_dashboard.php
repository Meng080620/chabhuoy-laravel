<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // The dashboard's today-revenue figure filters by captured status AND
            // a placed_at range. A composite (status, placed_at) lets MySQL seek
            // straight to "today's captured orders" instead of scanning every
            // captured order ever and filtering the date in memory.
            //
            // The standalone `status` index is dropped: it's the left prefix of
            // this composite, so keeping both just doubles the write cost for the
            // status histogram / pure-status lookups the composite already serves.
            $table->dropIndex(['status']);
            $table->index(['status', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'placed_at']);
            $table->index('status');
        });
    }
};
