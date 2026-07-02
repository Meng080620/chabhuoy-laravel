<?php

namespace Database\Factories;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(BannerType::cases())->value,
            'title' => fake()->words(3, true),
            'subtitle' => fake()->sentence(),
            'image_path' => null,
            'link_url' => fake()->url(),
            'cta_label' => 'Shop now',
            'position' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
