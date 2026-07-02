<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_stores', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('caption')->nullable();
            // Path on the 'public' disk; the Resource turns it into a full URL.
            $table->string('logo_path')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_stores');
    }
};
