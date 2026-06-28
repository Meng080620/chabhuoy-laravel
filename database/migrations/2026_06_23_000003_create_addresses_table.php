<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();         // "Home", "Office"
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 120);
            $table->string('postal_code', 20);
            $table->string('country', 2)->default('KH'); // ISO 3166-1 alpha-2
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        // At most one default address per user — enforced by the database, not
        // application code, so a race can't leave a user with two defaults.
        // Partial unique index: valid on both PostgreSQL (prod) and SQLite (tests).
        DB::statement(
            'CREATE UNIQUE INDEX addresses_one_default_per_user ON addresses (user_id) WHERE is_default'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
