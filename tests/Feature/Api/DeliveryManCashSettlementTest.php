<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManCashSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_rider_can_settle_collected_cash(): void
    {
        $rider = DeliveryMan::factory()->create(['cash_in_hand' => '45.00']);
        $this->actAsDeliveryMan($rider);

        $this->postJson('/api/delivery-man/make-collected-cash-payment', ['amount' => '45.00'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '45.00');

        $this->assertSame('0.00', (string) $rider->refresh()->cash_in_hand);
        $this->assertDatabaseHas('delivery_cash_settlements', [
            'delivery_man_id' => $rider->id,
            'amount' => '45.00',
        ]);
    }

    public function test_a_rider_cannot_settle_more_than_they_currently_hold(): void
    {
        $rider = DeliveryMan::factory()->create(['cash_in_hand' => '10.00']);
        $this->actAsDeliveryMan($rider);

        $this->postJson('/api/delivery-man/make-collected-cash-payment', ['amount' => '10.01'])
            ->assertStatus(422);

        $this->assertSame('10.00', (string) $rider->refresh()->cash_in_hand);
    }

    public function test_admin_can_list_and_filter_cash_settlements(): void
    {
        $riderA = DeliveryMan::factory()->create(['cash_in_hand' => '30.00']);
        $riderB = DeliveryMan::factory()->create(['cash_in_hand' => '15.00']);

        $this->actAsDeliveryMan($riderA);
        $this->postJson('/api/delivery-man/make-collected-cash-payment', ['amount' => '30.00'])->assertCreated();

        $this->actAsDeliveryMan($riderB);
        $this->postJson('/api/delivery-man/make-collected-cash-payment', ['amount' => '15.00'])->assertCreated();

        $this->actAsAdmin();

        $this->getJson('/api/admin/delivery-cash-settlements')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/admin/delivery-cash-settlements?delivery_man_id={$riderA->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '30.00');
    }
}
