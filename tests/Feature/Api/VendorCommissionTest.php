<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * v1 paid vendors 100% of every delivered line — no platform commission
 * existed anywhere in the schema. This locks in the fix: a per-vendor
 * commission_rate, applied and frozen on each line at credit-on-delivery
 * time, admin-settable via a dedicated endpoint.
 */
class VendorCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_vendors_default_to_the_platforms_standard_commission_rate(): void
    {
        // Insert below the factory so the DB column default is exercised
        // directly, not whatever the factory happens to set.
        $userId = User::factory()->vendor()->create()->id;
        $id = DB::table('vendors')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'name' => 'No Explicit Rate Co',
            'status' => Vendor::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('10.00', (string) Vendor::findOrFail($id)->commission_rate);
    }

    public function test_delivering_a_line_credits_the_vendor_net_of_commission(): void
    {
        $vendor = Vendor::factory()->commissionRate('10.00')->create(['payout_balance' => 0]);
        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'line_total' => '20.00',
        ]);

        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());
        $this->patchJson("/api/vendor/orders/{$order->uuid}", ['status' => FulfillmentStatus::Delivered->value])
            ->assertOk();

        // 10% of 20.00 is 2.00 commission; the vendor keeps the remaining 18.00.
        $this->assertSame('18.00', $vendor->refresh()->payout_balance);
    }

    public function test_commission_amount_is_frozen_on_the_delivered_line(): void
    {
        $vendor = Vendor::factory()->commissionRate('15.00')->create(['payout_balance' => 0]);
        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        $line = OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'line_total' => '40.00',
        ]);

        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());
        $this->patchJson("/api/vendor/orders/{$order->uuid}", ['status' => FulfillmentStatus::Delivered->value])
            ->assertOk();

        $this->assertSame('6.00', (string) $line->refresh()->commission_amount);

        // Changing the vendor's rate afterwards must not rewrite the frozen line.
        $vendor->update(['commission_rate' => '50.00']);
        $this->assertSame('6.00', (string) $line->refresh()->commission_amount);
    }

    public function test_admin_can_update_a_vendors_commission_rate(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->commissionRate('10.00')->create();

        Sanctum::actingAs($admin, $admin->role->abilities());

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}/commission", ['commission_rate' => '12.50'])
            ->assertOk()
            ->assertJsonPath('data.commission_rate', '12.50');

        $this->assertSame('12.50', (string) $vendor->refresh()->commission_rate);
    }

    public function test_commission_rate_must_be_between_0_and_100(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();

        Sanctum::actingAs($admin, $admin->role->abilities());

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}/commission", ['commission_rate' => '150'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission_rate']);

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}/commission", ['commission_rate' => '-5'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commission_rate']);
    }

    public function test_non_admin_cannot_update_commission_rate(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->patchJson("/api/admin/vendors/{$vendor->uuid}/commission", ['commission_rate' => '5.00'])
            ->assertForbidden();

        $this->assertSame('10.00', (string) $vendor->refresh()->commission_rate);
    }

    public function test_vendor_earnings_endpoint_reports_their_commission_rate(): void
    {
        $vendor = Vendor::factory()->commissionRate('7.50')->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson('/api/vendor/earnings')
            ->assertOk()
            ->assertJsonPath('data.commission_rate', '7.50');
    }
}
