<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // NB: do NOT use WithoutModelEvents here — HasUuid populates the `uuid`
    // column via the model `creating` event, which that trait would silence.

    /**
     * Seed a small but complete marketplace: one admin, a handful of active
     * vendors each with a catalogue, and a known customer for manual testing.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
        ]);

        $customer = User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
        ]);
        $customer->cart()->create();

        $categories = Category::factory()->count(4)->create();

        Vendor::factory()
            ->count(3)
            ->create()
            ->each(function (Vendor $vendor) use ($categories): void {
                Product::factory()
                    ->count(8)
                    ->for($vendor)
                    ->state(fn () => ['category_id' => $categories->random()->id])
                    ->create();
            });
    }
}
