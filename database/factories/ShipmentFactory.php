<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'carrier' => fake()->randomElement(['DHL', 'J&T', 'VET Express']),
            'tracking_number' => strtoupper(fake()->bothify('??-#####')),
            'shipped_at' => now(),
        ];
    }
}
