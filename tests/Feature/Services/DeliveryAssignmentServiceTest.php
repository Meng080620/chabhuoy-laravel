<?php

namespace Tests\Feature\Services;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the event/listener hook that turns a vendor's "shipped" transition
 * into a rider-assignable DeliveryAssignment — the riskiest new integration
 * point, since it must never duplicate assignments or interfere with the
 * existing vendor payout/fulfillment machine.
 */
class DeliveryAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Order, 1: OrderItem} */
    private function pendingLineFor(Vendor $vendor, PaymentMethod $method = PaymentMethod::Card): array
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create(['payment_method' => $method]);
        $line = OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Pending,
            'quantity' => 2,
            'unit_price' => 20,
            'line_total' => 40,
        ]);

        return [$order, $line];
    }

    public function test_shipping_a_line_creates_exactly_one_available_assignment_for_that_vendor_parcel(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor);

        app(OrderService::class)->fulfilVendorLines($order, $vendor, FulfillmentStatus::Shipped);

        $this->assertDatabaseCount('delivery_assignments', 1);
        $assignment = DeliveryAssignment::firstOrFail();
        $this->assertSame($order->id, $assignment->order_id);
        $this->assertSame($vendor->id, $assignment->vendor_id);
        $this->assertSame(DeliveryAssignmentStatus::Available, $assignment->status);
        $this->assertSame(config('delivery.flat_fee'), (float) $assignment->delivery_fee);
    }

    public function test_cod_orders_carry_the_line_total_forward_as_cod_amount(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor, PaymentMethod::Cod);

        app(OrderService::class)->fulfilVendorLines($order, $vendor, FulfillmentStatus::Shipped);

        $assignment = DeliveryAssignment::firstOrFail();
        $this->assertSame('40.00', (string) $assignment->cod_amount);
    }

    public function test_card_orders_carry_zero_cod_amount(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor, PaymentMethod::Card);

        app(OrderService::class)->fulfilVendorLines($order, $vendor, FulfillmentStatus::Shipped);

        $assignment = DeliveryAssignment::firstOrFail();
        $this->assertSame('0.00', (string) $assignment->cod_amount);
    }

    public function test_a_repeat_shipped_call_does_not_duplicate_the_assignment(): void
    {
        $vendor = Vendor::factory()->create();
        [$order] = $this->pendingLineFor($vendor);

        $service = app(OrderService::class);

        // First ship, no tracking.
        $service->fulfilVendorLines($order, $vendor, FulfillmentStatus::Shipped);
        // Tracking-only correction re-PATCH (lines already Shipped -> no-op on lines,
        // but the event still fires unconditionally on every Shipped call).
        $service->fulfilVendorLines($order, $vendor, FulfillmentStatus::Shipped, 'DHL', 'DHL-1');

        $this->assertDatabaseCount('delivery_assignments', 1);
    }

    public function test_admin_cancellation_cancels_non_terminal_assignments_but_leaves_delivered_ones(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        // Paid order with one still-Pending line -> legal to cancel.
        $order = Order::factory()->status(OrderStatus::Paid)->create();
        OrderItem::factory()->forVendor($vendorA)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Pending,
        ]);

        // Seed two assignments directly (bypassing the listener) so this test
        // isolates the cancellation listener's own scoping logic.
        $available = DeliveryAssignment::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendorA->id,
        ]);
        $delivered = DeliveryAssignment::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $vendorB->id,
            'status' => DeliveryAssignmentStatus::Delivered,
        ]);

        app(OrderService::class)->cancelAsAdmin($order);

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $available->id,
            'status' => DeliveryAssignmentStatus::Cancelled->value,
        ]);
        // The already-Delivered assignment is untouched by the cancellation.
        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $delivered->id,
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ]);
    }
}
