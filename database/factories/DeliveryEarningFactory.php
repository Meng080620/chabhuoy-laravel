<?php

namespace Database\Factories;

use App\Enums\DeliveryEarningStatus;
use App\Models\DeliveryEarning;
use App\Models\DeliveryMan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryEarning>
 */
class DeliveryEarningFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_man_id' => DeliveryMan::factory(),
            'amount' => fake()->randomFloat(2, 1, 100),
            'status' => DeliveryEarningStatus::Completed,
            'reference' => null,
            'processed_at' => now(),
        ];
    }
}
