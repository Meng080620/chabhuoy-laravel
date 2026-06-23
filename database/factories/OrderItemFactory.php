<?php

namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $vendor = Vendor::factory();
        $quantity = fake()->numberBetween(1, 4);
        $unitPrice = fake()->randomFloat(2, 1, 200);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->for($vendor),
            'vendor_id' => $vendor,
            'product_name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => bcmul((string) $unitPrice, (string) $quantity, 2),
            'status' => FulfillmentStatus::Pending,
        ];
    }

    /**
     * Attach the line to an existing vendor (keeps product + line vendor aligned).
     */
    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn () => [
            'vendor_id' => $vendor->id,
            'product_id' => Product::factory()->for($vendor),
        ]);
    }

    public function status(FulfillmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
