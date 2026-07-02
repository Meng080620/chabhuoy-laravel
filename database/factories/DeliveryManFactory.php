<?php

namespace Database\Factories;

use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryMan>
 */
class DeliveryManFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->deliveryMan(),
            'name' => fake()->name(),
            'vehicle_type' => fake()->randomElement(['bike', 'scooter', 'car']),
            'status' => DeliveryMan::STATUS_ACTIVE,
            'wallet_balance' => 0,
            'cash_in_hand' => 0,
            // Active by default so accept-flow tests don't need extra setup.
            'is_online' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => DeliveryMan::STATUS_PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => DeliveryMan::STATUS_SUSPENDED]);
    }

    public function offline(): static
    {
        return $this->state(fn () => ['is_online' => false]);
    }
}
