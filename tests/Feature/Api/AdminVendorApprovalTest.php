<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_admin_can_approve_a_pending_vendor(): void
    {
        $vendor = Vendor::factory()->pending()->create();
        $this->actAsAdmin();

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}", [
            'status' => Vendor::STATUS_ACTIVE,
        ])->assertOk()
            ->assertJsonPath('data.status', Vendor::STATUS_ACTIVE);

        $this->assertSame(Vendor::STATUS_ACTIVE, $vendor->refresh()->status);
    }

    public function test_suspending_a_vendor_locks_them_out_of_the_vendor_area(): void
    {
        $vendor = Vendor::factory()->create(); // active
        $this->actAsAdmin();

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}", [
            'status' => Vendor::STATUS_SUSPENDED,
        ])->assertOk();

        // The suspension has teeth: the vendor's own token no longer reaches
        // the vendor area, enforced by EnsureVendorRole on the next request.
        Sanctum::actingAs($vendor->user->refresh(), $vendor->user->role->abilities());
        $this->getJson('/api/vendor/products')->assertForbidden();
    }

    public function test_admin_can_filter_the_approval_queue_by_status(): void
    {
        Vendor::factory()->pending()->count(2)->create();
        Vendor::factory()->count(3)->create(); // active

        $this->actAsAdmin();

        $this->getJson('/api/admin/vendors?status='.Vendor::STATUS_PENDING)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $vendor = Vendor::factory()->pending()->create();
        $this->actAsAdmin();

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}", [
            'status' => 'banished',
        ])->assertStatus(422);

        $this->assertSame(Vendor::STATUS_PENDING, $vendor->refresh()->status);
    }

    public function test_a_non_admin_cannot_change_vendor_status(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        // A vendor trying to self-approve.
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}", [
            'status' => Vendor::STATUS_ACTIVE,
        ])->assertForbidden();

        $this->assertSame(Vendor::STATUS_PENDING, $vendor->refresh()->status);
    }
}
