<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function actAsVendor(Vendor $vendor): void
    {
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());
    }

    /**
     * A Paid order carrying one Pending line for the given vendor.
     *
     * @return array{0: Order, 1: OrderItem}
     */
    private function pendingLineFor(Vendor $vendor): array
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create();
        $line = OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Pending,
        ]);

        return [$order, $line];
    }

    public function test_shipping_with_tracking_records_a_shipment(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor);

        $this->actAsVendor($vendor);

        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
            'carrier' => 'VET Express',
            'tracking_number' => 'VET-12345',
        ])->assertOk()
            ->assertJsonPath('data.shipments.0.carrier', 'VET Express')
            ->assertJsonPath('data.shipments.0.tracking_number', 'VET-12345');

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'carrier' => 'VET Express',
            'tracking_number' => 'VET-12345',
        ]);
    }

    public function test_shipping_without_tracking_is_allowed_and_records_no_shipment(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor);

        $this->actAsVendor($vendor);

        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
        ])->assertOk();

        // Tracking is optional — a vendor can ship before a number exists.
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_a_later_update_corrects_tracking_without_duplicating_the_shipment(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor);

        $this->actAsVendor($vendor);

        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
            'tracking_number' => 'WRONG-1',
        ])->assertOk();

        $shippedAt = Shipment::firstOrFail()->shipped_at;

        // Re-PATCH shipped (lines already shipped → no-op) only to fix tracking.
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
            'carrier' => 'DHL',
            'tracking_number' => 'DHL-RIGHT-9',
        ])->assertOk();

        $this->assertDatabaseCount('shipments', 1);
        $shipment = Shipment::firstOrFail();
        $this->assertSame('DHL-RIGHT-9', $shipment->tracking_number);
        $this->assertSame('DHL', $shipment->carrier);
        // shipped_at is set once, at first ship — a correction doesn't reset it.
        $this->assertEquals($shippedAt, $shipment->shipped_at);
    }

    public function test_each_vendor_on_a_shared_order_has_its_own_shipment(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $order = Order::factory()->status(OrderStatus::Paid)->create();
        OrderItem::factory()->forVendor($vendorA)->create(['order_id' => $order->id, 'status' => FulfillmentStatus::Pending]);
        OrderItem::factory()->forVendor($vendorB)->create(['order_id' => $order->id, 'status' => FulfillmentStatus::Pending]);

        $this->actAsVendor($vendorA);
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
            'tracking_number' => 'A-111',
        ])->assertOk()
            // The vendor only sees their own parcel back.
            ->assertJsonCount(1, 'data.shipments')
            ->assertJsonPath('data.shipments.0.tracking_number', 'A-111');

        $this->assertDatabaseHas('shipments', ['vendor_id' => $vendorA->id, 'tracking_number' => 'A-111']);
        $this->assertDatabaseMissing('shipments', ['vendor_id' => $vendorB->id]);
    }

    public function test_tracking_cannot_be_attached_to_a_delivered_update(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
        ]);

        $this->actAsVendor($vendor);

        // Tracking belongs to the shipping step, not delivery.
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Delivered->value,
            'tracking_number' => 'LATE-1',
        ])->assertStatus(422);
    }

    public function test_customer_sees_shipment_tracking_on_their_order(): void
    {
        $customer = User::factory()->create();
        $vendor = Vendor::factory()->create();

        $order = Order::factory()->status(OrderStatus::Paid)->create(['user_id' => $customer->id]);
        OrderItem::factory()->forVendor($vendor)->create(['order_id' => $order->id, 'status' => FulfillmentStatus::Pending]);

        $this->actAsVendor($vendor);
        $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Shipped->value,
            'carrier' => 'J&T',
            'tracking_number' => 'JT-77',
        ])->assertOk();

        Sanctum::actingAs($customer, $customer->role->abilities());

        $this->getJson("/api/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.shipments.0.carrier', 'J&T')
            ->assertJsonPath('data.shipments.0.tracking_number', 'JT-77')
            ->assertJsonPath('data.shipments.0.vendor.name', $vendor->name);
    }
}
