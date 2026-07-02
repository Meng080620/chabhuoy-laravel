<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManPresenceTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    public function test_update_active_status_toggles_is_online(): void
    {
        $rider = DeliveryMan::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->patchJson('/api/delivery-man/update-active-status', ['is_online' => false])
            ->assertOk()
            ->assertJsonPath('data.is_online', false);

        $this->assertFalse($rider->refresh()->is_online);
    }

    public function test_record_location_data_writes_last_lat_lng(): void
    {
        $rider = DeliveryMan::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->patchJson('/api/delivery-man/record-location-data', [
            'lat' => 11.55, 'lng' => 104.92,
        ])->assertOk();

        $rider->refresh();
        $this->assertSame('11.5500000', (string) $rider->last_lat);
        $this->assertSame('104.9200000', (string) $rider->last_lng);
    }

    public function test_update_fcm_token_writes_the_token(): void
    {
        $rider = DeliveryMan::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->patchJson('/api/delivery-man/update-fcm-token', ['fcm_token' => 'abc123'])
            ->assertOk();

        $this->assertSame('abc123', $rider->refresh()->fcm_token);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $rider = DeliveryMan::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->patchJson('/api/delivery-man/record-location-data', [
            'lat' => 200, 'lng' => 104.92,
        ])->assertStatus(422);
    }
}
