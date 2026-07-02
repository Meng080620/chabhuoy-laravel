<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAssignmentStatus;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManAcceptOrderTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    public function test_an_online_rider_can_accept_an_available_assignment_and_gps_is_recorded(): void
    {
        $rider = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 11.5564,
            'lng' => 104.9282,
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryAssignmentStatus::Accepted->value);

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'delivery_man_id' => $rider->id,
            'status' => DeliveryAssignmentStatus::Accepted->value,
        ]);
        $this->assertSame('11.5564000', (string) $rider->refresh()->last_lat);
    }

    public function test_an_offline_rider_cannot_accept(): void
    {
        $rider = DeliveryMan::factory()->offline()->create();
        $assignment = DeliveryAssignment::factory()->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 11.5, 'lng' => 104.9,
        ])->assertStatus(422);

        $this->assertDatabaseHas('delivery_assignments', ['id' => $assignment->id, 'delivery_man_id' => null]);
    }

    public function test_a_rider_at_the_concurrency_limit_cannot_accept_another(): void
    {
        $rider = DeliveryMan::factory()->create();
        $limit = (int) config('delivery.max_concurrent_orders');
        DeliveryAssignment::factory()->count($limit)->accepted($rider)->create();

        $newAssignment = DeliveryAssignment::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/accept-order/{$newAssignment->uuid}", [
            'lat' => 11.5, 'lng' => 104.9,
        ])->assertStatus(422);
    }

    public function test_accepting_a_cod_job_that_would_exceed_the_cash_ceiling_is_rejected(): void
    {
        $ceiling = (float) config('delivery.max_cash_in_hand');
        $rider = DeliveryMan::factory()->create(['cash_in_hand' => $ceiling]);
        $assignment = DeliveryAssignment::factory()->codOf('10.00')->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 11.5, 'lng' => 104.9,
        ])->assertStatus(422);
    }

    public function test_a_second_rider_cannot_accept_an_already_accepted_assignment(): void
    {
        $riderA = DeliveryMan::factory()->create();
        $riderB = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->accepted($riderA)->create();

        $this->actAsDeliveryMan($riderB);

        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 11.5, 'lng' => 104.9,
        ])->assertStatus(422);

        $this->assertSame($riderA->id, $assignment->refresh()->delivery_man_id);
    }

    public function test_a_rider_outside_the_service_zone_cannot_accept(): void
    {
        config([
            'delivery.service_zone.enabled' => true,
            'delivery.service_zone.center_lat' => 11.5564,   // Phnom Penh
            'delivery.service_zone.center_lng' => 104.9282,
            'delivery.service_zone.radius_km' => 10.0,
        ]);

        $rider = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->create();

        $this->actAsDeliveryMan($rider);

        // Bangkok — ~530 km from the zone centre, well outside the 10 km radius.
        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 13.7563, 'lng' => 100.5018,
        ])->assertStatus(422);

        $this->assertDatabaseHas('delivery_assignments', ['id' => $assignment->id, 'delivery_man_id' => null]);
    }

    public function test_a_rider_inside_the_service_zone_can_accept_when_the_guard_is_enabled(): void
    {
        config([
            'delivery.service_zone.enabled' => true,
            'delivery.service_zone.center_lat' => 11.5564,
            'delivery.service_zone.center_lng' => 104.9282,
            'delivery.service_zone.radius_km' => 10.0,
        ]);

        $rider = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->create();

        $this->actAsDeliveryMan($rider);

        // ~0.5 km from the centre — inside the radius, so the guard must pass.
        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 11.5600, 'lng' => 104.9300,
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryAssignmentStatus::Accepted->value);
    }

    public function test_lat_and_lng_are_validated(): void
    {
        $rider = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/accept-order/{$assignment->uuid}", [
            'lat' => 999, 'lng' => 104.9,
        ])->assertStatus(422);
    }
}
