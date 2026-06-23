<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'total' => fake()->randomFloat(2, 10, 1000),
            'placed_at' => now(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
