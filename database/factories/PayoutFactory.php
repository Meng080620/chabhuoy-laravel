<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'amount' => fake()->randomFloat(2, 1, 500),
            'status' => PayoutStatus::Completed,
            'reference' => null,
            'processed_at' => now(),
        ];
    }
}
