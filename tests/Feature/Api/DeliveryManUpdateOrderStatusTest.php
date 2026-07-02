<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryManUpdateOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function actAsDeliveryMan(DeliveryMan $deliveryMan): void
    {
        Sanctum::actingAs($deliveryMan->user, $deliveryMan->user->role->abilities());
    }

    /** @return array{0: DeliveryAssignment, 1: Order, 2: Vendor} */
    private function pickedUpAssignment(DeliveryMan $rider, PaymentMethod $method = PaymentMethod::Card, string $lineTotal = '20.00'): array
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->status(OrderStatus::Shipped)->create(['payment_method' => $method]);
        OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'unit_price' => $lineTotal,
            'quantity' => 1,
            'line_total' => $lineTotal,
        ]);

        $assignment = DeliveryAssignment::factory()->pickedUp($rider)->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'delivery_fee' => '3.00',
            'cod_amount' => $method === PaymentMethod::Cod ? $lineTotal : 0,
        ]);

        return [$assignment, $order, $vendor];
    }

    public function test_picked_up_transition_stamps_the_timestamp(): void
    {
        $rider = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->accepted($rider)->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::PickedUp->value,
        ])->assertOk();

        $this->assertNotNull($assignment->refresh()->picked_up_at);
    }

    public function test_delivering_a_card_order_credits_only_the_wallet(): void
    {
        $rider = DeliveryMan::factory()->create();
        [$assignment, , $vendor] = $this->pickedUpAssignment($rider, PaymentMethod::Card, '20.00');

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ])->assertOk();

        $rider->refresh();
        $this->assertSame('3.00', (string) $rider->wallet_balance);
        $this->assertSame('0.00', (string) $rider->cash_in_hand);
        // The existing vendor payout machinery still fires exactly once.
        $this->assertSame('20.00', (string) $vendor->refresh()->payout_balance);
    }

    public function test_delivering_a_cod_order_credits_both_wallet_and_cash_in_hand(): void
    {
        $rider = DeliveryMan::factory()->create();
        [$assignment] = $this->pickedUpAssignment($rider, PaymentMethod::Cod, '20.00');

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ])->assertOk();

        $rider->refresh();
        $this->assertSame('3.00', (string) $rider->wallet_balance);
        $this->assertSame('20.00', (string) $rider->cash_in_hand);
    }

    public function test_delivering_advances_the_underlying_order_item_to_delivered(): void
    {
        $rider = DeliveryMan::factory()->create();
        [$assignment, $order] = $this->pickedUpAssignment($rider);

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ])->assertOk();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Delivered->value,
        ]);
    }

    public function test_returning_a_picked_up_order_restocks_the_line_and_credits_nobody(): void
    {
        $rider = DeliveryMan::factory()->create();
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock' => 5]);
        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        $line = OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'status' => FulfillmentStatus::Shipped,
            'quantity' => 2,
            'unit_price' => '20.00',
            'line_total' => '40.00',
        ]);
        $assignment = DeliveryAssignment::factory()->pickedUp($rider)->create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'delivery_fee' => '3.00',
        ]);

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Returned->value,
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryAssignmentStatus::Returned->value);

        // The undeliverable goods physically came back → stock is restored.
        $this->assertSame(7, $product->refresh()->stock);
        // Recorded as its own terminal line state, distinct from an admin Cancel.
        $this->assertSame(FulfillmentStatus::Returned, $line->refresh()->status);
        // A return is not a delivery: the vendor is not paid...
        $this->assertSame('0.00', (string) $vendor->refresh()->payout_balance);
        // ...and neither is the rider.
        $rider->refresh();
        $this->assertSame('0.00', (string) $rider->wallet_balance);
        $this->assertSame('0.00', (string) $rider->cash_in_hand);
    }

    public function test_delivery_requires_a_proof_photo_when_configured(): void
    {
        config(['delivery.proof_photo_required' => true]);

        $rider = DeliveryMan::factory()->create();
        [$assignment] = $this->pickedUpAssignment($rider);

        $this->actAsDeliveryMan($rider);

        // No file attached -> rejected before any state change.
        $this->patch("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(
            DeliveryAssignmentStatus::PickedUp,
            $assignment->refresh()->status,
        );
    }

    public function test_delivery_with_a_proof_photo_stores_it_and_records_the_path(): void
    {
        Storage::fake('public');
        config(['delivery.proof_photo_required' => true]);

        $rider = DeliveryMan::factory()->create();
        [$assignment] = $this->pickedUpAssignment($rider);

        $this->actAsDeliveryMan($rider);

        $this->patch("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
            'proof_photo' => UploadedFile::fake()->image('doorstep.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $assignment->refresh()->proof_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(DeliveryAssignmentStatus::Delivered, $assignment->status);
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $rider = DeliveryMan::factory()->create();
        // Accepted -> Delivered directly is illegal (must go through PickedUp first).
        $assignment = DeliveryAssignment::factory()->accepted($rider)->create();

        $this->actAsDeliveryMan($rider);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::Delivered->value,
        ])->assertStatus(422);
    }

    public function test_a_rider_cannot_advance_another_riders_assignment(): void
    {
        $riderA = DeliveryMan::factory()->create();
        $riderB = DeliveryMan::factory()->create();
        $assignment = DeliveryAssignment::factory()->accepted($riderA)->create();

        $this->actAsDeliveryMan($riderB);

        $this->patchJson("/api/delivery-man/update-order-status/{$assignment->uuid}", [
            'status' => DeliveryAssignmentStatus::PickedUp->value,
        ])->assertStatus(404);
    }
}
