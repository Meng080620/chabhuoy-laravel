<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAssignmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManOrderListsTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    public function test_latest_orders_returns_only_the_unassigned_pool(): void
    {
        $rider = DeliveryMan::factory()->create();
        DeliveryAssignment::factory()->count(2)->create(); // Available, unassigned
        DeliveryAssignment::factory()->accepted($rider)->create(); // taken

        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/latest-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_current_orders_returns_only_this_riders_non_terminal_jobs(): void
    {
        $rider = DeliveryMan::factory()->create();
        $otherRider = DeliveryMan::factory()->create();

        DeliveryAssignment::factory()->accepted($rider)->create();
        DeliveryAssignment::factory()->pickedUp($rider)->create();
        DeliveryAssignment::factory()->create([
            'delivery_man_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::Delivered,
        ]);
        // Another rider's job must never leak into this list.
        DeliveryAssignment::factory()->accepted($otherRider)->create();

        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/current-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_all_orders_returns_full_history_including_terminal_states(): void
    {
        $rider = DeliveryMan::factory()->create();

        DeliveryAssignment::factory()->accepted($rider)->create();
        DeliveryAssignment::factory()->create([
            'delivery_man_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::Delivered,
        ]);
        DeliveryAssignment::factory()->create([
            'delivery_man_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::Cancelled,
        ]);

        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/all-orders')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
