<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'idempotency_key' => 'order_'.fake()->uuid(),
            'reference' => 'txn_'.Str::lower(Str::random(24)),
            'status' => PaymentStatus::Succeeded,
            'amount' => fake()->randomFloat(2, 1, 1000),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'reference' => null,
        ]);
    }
}
