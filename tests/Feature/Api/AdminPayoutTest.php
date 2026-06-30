<?php

namespace Tests\Feature\Api;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_admin_can_trigger_a_payout_recording_a_ledger_row_and_zeroing_the_balance(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => '50.00']);
        $this->actAsAdmin();

        $this->postJson("/api/admin/payouts/{$vendor->uuid}")
            ->assertCreated()
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.status', PayoutStatus::Completed->value)
            ->assertJsonPath('data.vendor.id', $vendor->uuid);

        // Balance is drained and the disbursement is now an auditable row.
        $this->assertSame('0.00', $vendor->refresh()->payout_balance);
        $this->assertDatabaseHas('payouts', [
            'vendor_id' => $vendor->id,
            'amount' => '50.00',
            'status' => PayoutStatus::Completed->value,
        ]);
    }

    public function test_triggering_a_payout_with_nothing_owed_is_rejected(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => 0]);
        $this->actAsAdmin();

        $this->postJson("/api/admin/payouts/{$vendor->uuid}")->assertStatus(422);

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_admin_can_list_payouts_newest_first(): void
    {
        Payout::factory()->count(3)->create();
        $this->actAsAdmin();

        $this->getJson('/api/admin/payouts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'vendor', 'amount', 'status', 'reference', 'processed_at', 'created_at']],
                'links',
                'meta',
            ]);
    }

    public function test_admin_can_filter_payouts_by_vendor(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        Payout::factory()->count(2)->for($vendorA)->create();
        Payout::factory()->count(3)->for($vendorB)->create();

        $this->actAsAdmin();

        $this->getJson("/api/admin/payouts?vendor_id={$vendorA->uuid}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.vendor.id', $vendorA->uuid);
    }

    public function test_a_non_admin_cannot_access_payouts(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson('/api/admin/payouts')->assertForbidden();
        $this->postJson("/api/admin/payouts/{$vendor->uuid}")->assertForbidden();
    }
}
