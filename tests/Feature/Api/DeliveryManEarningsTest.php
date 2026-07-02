<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryEarningStatus;
use App\Models\DeliveryCashSettlement;
use App\Models\DeliveryEarning;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManEarningsTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    public function test_earnings_endpoint_returns_the_two_ledger_summary(): void
    {
        $rider = DeliveryMan::factory()->create(['wallet_balance' => '9.00', 'cash_in_hand' => '45.00']);
        DeliveryEarning::factory()->create([
            'delivery_man_id' => $rider->id,
            'amount' => '12.00',
            'status' => DeliveryEarningStatus::Completed,
        ]);
        DeliveryCashSettlement::factory()->create(['delivery_man_id' => $rider->id, 'amount' => '8.00']);

        $this->actAsDeliveryMan($rider);

        $this->getJson('/api/delivery-man/earnings')
            ->assertOk()
            ->assertJsonPath('data.wallet_balance', '9.00')
            ->assertJsonPath('data.cash_in_hand', '45.00')
            // net_position can legitimately go negative — cash held can exceed wallet owed.
            ->assertJsonPath('data.net_position', '-36.00')
            ->assertJsonPath('data.total_paid_out', '12.00')
            ->assertJsonPath('data.total_cash_settled', '8.00')
            ->assertJsonCount(1, 'data.recent_payouts')
            ->assertJsonCount(1, 'data.recent_cash_settlements');
    }

    public function test_earnings_only_reflects_the_authed_riders_own_money(): void
    {
        $me = DeliveryMan::factory()->create(['wallet_balance' => '9.00']);
        $other = DeliveryMan::factory()->create(['wallet_balance' => '999.00']);
        DeliveryEarning::factory()->create(['delivery_man_id' => $other->id, 'amount' => '777.00']);

        $this->actAsDeliveryMan($me);

        $this->getJson('/api/delivery-man/earnings')
            ->assertOk()
            ->assertJsonPath('data.wallet_balance', '9.00')
            ->assertJsonPath('data.total_paid_out', '0.00')
            ->assertJsonCount(0, 'data.recent_payouts');
    }
}
