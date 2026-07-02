<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryEarningStatus;
use App\Models\DeliveryEarning;
use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDeliveryEarningTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_admin_can_trigger_a_disbursement_recording_a_ledger_row_and_zeroing_the_wallet(): void
    {
        $rider = DeliveryMan::factory()->create(['wallet_balance' => '50.00']);
        $this->actAsAdmin();

        $this->postJson("/api/admin/delivery-earnings/{$rider->uuid}")
            ->assertCreated()
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.status', DeliveryEarningStatus::Completed->value)
            ->assertJsonPath('data.delivery_man.id', $rider->uuid);

        $this->assertSame('0.00', (string) $rider->refresh()->wallet_balance);
        $this->assertDatabaseHas('delivery_earnings', [
            'delivery_man_id' => $rider->id,
            'amount' => '50.00',
            'status' => DeliveryEarningStatus::Completed->value,
        ]);
    }

    public function test_disbursing_with_nothing_owed_is_rejected(): void
    {
        $rider = DeliveryMan::factory()->create(['wallet_balance' => 0]);
        $this->actAsAdmin();

        $this->postJson("/api/admin/delivery-earnings/{$rider->uuid}")->assertStatus(422);

        $this->assertDatabaseCount('delivery_earnings', 0);
    }

    public function test_admin_can_list_and_filter_delivery_earnings(): void
    {
        $riderA = DeliveryMan::factory()->create();
        $riderB = DeliveryMan::factory()->create();
        DeliveryEarning::factory()->count(2)->create(['delivery_man_id' => $riderA->id]);
        DeliveryEarning::factory()->count(3)->create(['delivery_man_id' => $riderB->id]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/delivery-earnings')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->getJson("/api/admin/delivery-earnings?delivery_man_id={$riderA->uuid}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.delivery_man.id', $riderA->uuid);
    }

    public function test_a_non_admin_cannot_access_delivery_earnings(): void
    {
        $rider = DeliveryMan::factory()->create();
        Sanctum::actingAs($rider->user, $rider->user->role->abilities());

        $this->getJson('/api/admin/delivery-earnings')->assertForbidden();
        $this->postJson("/api/admin/delivery-earnings/{$rider->uuid}")->assertForbidden();
    }
}
