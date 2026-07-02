<?php

namespace Database\Factories;

use App\Models\DeliveryCashSettlement;
use App\Models\DeliveryMan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryCashSettlement>
 */
class DeliveryCashSettlementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_man_id' => DeliveryMan::factory(),
            'amount' => fake()->randomFloat(2, 1, 100),
            'settled_at' => now(),
        ];
    }
}
