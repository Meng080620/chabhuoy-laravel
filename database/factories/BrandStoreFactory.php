<?php

namespace Database\Factories;

use App\Models\BrandStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandStore>
 */
class BrandStoreFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'caption' => 'Official store',
            'logo_path' => null,
            'link_url' => fake()->url(),
            'position' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
