<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            // Storefront slot: hero | promo | eco | seasonal (App\Enums\BannerType).
            $table->string('type')->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            // Path on the 'public' disk; the Resource turns it into a full URL.
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('cta_label')->nullable();
            // Ordering within a slot; the storefront renders ascending.
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'type', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
