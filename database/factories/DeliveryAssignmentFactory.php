<?php

namespace Database\Factories;

use App\Enums\DeliveryAssignmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAssignment>
 */
class DeliveryAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory(),
            'status' => DeliveryAssignmentStatus::Available,
            'delivery_fee' => config('delivery.flat_fee'),
            'cod_amount' => 0,
        ];
    }

    public function accepted(DeliveryMan $deliveryMan): static
    {
        return $this->state(fn () => [
            'delivery_man_id' => $deliveryMan->id,
            'status' => DeliveryAssignmentStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function pickedUp(DeliveryMan $deliveryMan): static
    {
        return $this->state(fn () => [
            'delivery_man_id' => $deliveryMan->id,
            'status' => DeliveryAssignmentStatus::PickedUp,
            'accepted_at' => now(),
            'picked_up_at' => now(),
        ]);
    }

    public function codOf(string $amount): static
    {
        return $this->state(fn () => ['cod_amount' => $amount]);
    }
}
