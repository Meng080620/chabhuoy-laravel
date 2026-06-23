<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build one order shared by two vendors: one line each.
     *
     * @return array{0: Order, 1: Vendor, 2: OrderItem, 3: Vendor, 4: OrderItem}
     */
    private function sharedOrder(): array
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create();

        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $lineA = OrderItem::factory()->forVendor($vendorA)->create(['order_id' => $order->id]);
        $lineB = OrderItem::factory()->forVendor($vendorB)->create(['order_id' => $order->id]);

        return [$order, $vendorA, $lineA, $vendorB, $lineB];
    }

    private function actAsVendor(Vendor $vendor): void
    {
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());
    }

    public function test_vendor_ships_only_their_own_lines_on_a_shared_order(): void
    {
        [$order, $vendorA, $lineA, $vendorB, $lineB] = $this->sharedOrder();

        $this->actAsVendor($vendorA);

        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
        ])->assertOk()
            // The vendor only ever sees their own line back.
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', FulfillmentStatus::Shipped->value);

        // A's line moved; B's line is untouched.
        $this->assertSame(FulfillmentStatus::Shipped, $lineA->refresh()->status);
        $this->assertSame(FulfillmentStatus::Pending, $lineB->refresh()->status);

        // Order is still Paid — not every line has shipped yet.
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_order_rolls_up_to_shipped_only_when_all_lines_ship(): void
    {
        [$order, $vendorA, $lineA, $vendorB, $lineB] = $this->sharedOrder();

        $this->actAsVendor($vendorA);
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
        ])->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $this->actAsVendor($vendorB);
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
        ])->assertOk();

        // Last vendor shipped -> order rolls up.
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }

    public function test_vendor_cannot_touch_an_order_they_have_no_line_on(): void
    {
        [$order, , , , $lineB] = $this->sharedOrder();

        // A third vendor with no stake in this order.
        $stranger = Vendor::factory()->create();
        $this->actAsVendor($stranger);

        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
        ])->assertNotFound();

        // Nothing changed.
        $this->assertSame(FulfillmentStatus::Pending, $lineB->refresh()->status);
    }

    public function test_illegal_transition_is_rejected_with_422(): void
    {
        [$order, $vendorA, $lineA] = $this->sharedOrder();
        $lineA->update(['status' => FulfillmentStatus::Pending]);

        $this->actAsVendor($vendorA);

        // Pending -> Delivered skips Shipped; the machine forbids it.
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Delivered->value,
        ])->assertStatus(422);

        $this->assertSame(FulfillmentStatus::Pending, $lineA->refresh()->status);
    }

    public function test_status_is_validated(): void
    {
        [$order, $vendorA] = $this->sharedOrder();
        $this->actAsVendor($vendorA);

        // 'pending' is not a vendor-settable status.
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => 'pending',
        ])->assertStatus(422);
    }
}
