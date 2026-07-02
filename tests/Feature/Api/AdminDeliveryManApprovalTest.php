<?php

namespace Tests\Feature\Api;

use App\Models\DeliveryMan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDeliveryManApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_admin_can_approve_a_pending_rider(): void
    {
        $rider = DeliveryMan::factory()->pending()->create();
        $this->actAsAdmin();

        $this->patchJson("/api/admin/delivery-men/{$rider->uuid}", [
            'status' => DeliveryMan::STATUS_ACTIVE,
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryMan::STATUS_ACTIVE);

        $this->assertSame(DeliveryMan::STATUS_ACTIVE, $rider->refresh()->status);
    }

    public function test_suspending_a_rider_locks_them_out_of_the_delivery_man_area(): void
    {
        $rider = DeliveryMan::factory()->create(); // active
        $this->actAsAdmin();

        $this->patchJson("/api/admin/delivery-men/{$rider->uuid}", [
            'status' => DeliveryMan::STATUS_SUSPENDED,
        ])->assertOk();

        Sanctum::actingAs($rider->user->refresh(), $rider->user->role->abilities());
        $this->getJson('/api/delivery-man/current-orders')->assertForbidden();
    }

    public function test_admin_can_filter_the_approval_queue_by_status(): void
    {
        DeliveryMan::factory()->pending()->count(2)->create();
        DeliveryMan::factory()->count(3)->create(); // active

        $this->actAsAdmin();

        $this->getJson('/api/admin/delivery-men?status='.DeliveryMan::STATUS_PENDING)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $rider = DeliveryMan::factory()->pending()->create();
        $this->actAsAdmin();

        $this->patchJson("/api/admin/delivery-men/{$rider->uuid}", [
            'status' => 'banished',
        ])->assertStatus(422);

        $this->assertSame(DeliveryMan::STATUS_PENDING, $rider->refresh()->status);
    }

    public function test_a_non_admin_cannot_change_rider_status(): void
    {
        $rider = DeliveryMan::factory()->pending()->create();

        Sanctum::actingAs($rider->user, $rider->user->role->abilities());

        $this->patchJson("/api/admin/delivery-men/{$rider->uuid}", [
            'status' => DeliveryMan::STATUS_ACTIVE,
        ])->assertForbidden();

        $this->assertSame(DeliveryMan::STATUS_PENDING, $rider->refresh()->status);
    }
}
