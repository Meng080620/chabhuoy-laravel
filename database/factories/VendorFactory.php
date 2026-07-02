<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vendor(),
            'name' => fake()->company(),
            'status' => Vendor::STATUS_ACTIVE,
            'payout_balance' => 0,
            'commission_rate' => '10.00',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => Vendor::STATUS_SUSPENDED]);
    }

    public function commissionRate(string $rate): static
    {
        return $this->state(fn () => ['commission_rate' => $rate]);
    }
}
