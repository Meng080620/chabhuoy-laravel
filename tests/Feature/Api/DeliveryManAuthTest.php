<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManAuthTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    public function test_a_non_rider_cannot_access_the_delivery_man_area(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, $customer->role->abilities());

        $this->getJson('/api/delivery-man/current-orders')->assertForbidden();
    }

    public function test_a_pending_rider_is_forbidden(): void
    {
        $rider = DeliveryMan::factory()->pending()->create();
        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/current-orders')->assertForbidden();
    }

    public function test_a_suspended_rider_is_forbidden(): void
    {
        $rider = DeliveryMan::factory()->suspended()->create();
        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/current-orders')->assertForbidden();
    }

    public function test_an_active_rider_is_allowed(): void
    {
        $rider = DeliveryMan::factory()->create();
        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/current-orders')->assertOk();
    }
}
