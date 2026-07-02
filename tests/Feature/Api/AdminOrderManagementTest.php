<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_admin_can_list_all_orders_across_customers(): void
    {
        Order::factory()->count(2)->create(); // each gets its own user

        $this->actAsAdmin();

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'status', 'total']],
                'meta' => ['total'],
            ]);
    }

    public function test_a_non_admin_cannot_list_orders(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, $customer->role->abilities());

        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        Order::factory()->status(OrderStatus::Paid)->count(2)->create();
        Order::factory()->status(OrderStatus::Cancelled)->create();

        $this->actAsAdmin();

        $this->getJson('/api/admin/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_search_orders_by_customer_name_email_and_order_uuid(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Zaytsev', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob Marley', 'email' => 'bob@example.com']);
        $aliceOrder = Order::factory()->for($alice)->create();
        Order::factory()->for($bob)->create();

        $this->actAsAdmin();

        // By customer name (partial, case-insensitive).
        $this->getJson('/api/admin/orders?search=zaytsev')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.email', 'alice@example.com');

        // By customer email.
        $this->getJson('/api/admin/orders?search=bob@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.name', 'Bob Marley');

        // By order uuid.
        $this->getJson("/api/admin/orders?search={$aliceOrder->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aliceOrder->uuid);
    }

    public function test_admin_can_cancel_a_paid_order_and_held_stock_is_restored(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock' => 10]);
        $order = Order::factory()->status(OrderStatus::Paid)->create();
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'quantity' => 2,
            'status' => FulfillmentStatus::Pending,
        ]);

        $this->actAsAdmin();

        $this->patchJson("/api/admin/orders/{$order->uuid}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(FulfillmentStatus::Cancelled, $order->items()->first()->status);
        // The two held units return to inventory.
        $this->assertSame(12, $product->refresh()->stock);
    }

    public function test_admin_cannot_cancel_a_delivered_order(): void
    {
        $order = Order::factory()->status(OrderStatus::Delivered)->create();

        $this->actAsAdmin();

        $this->patchJson("/api/admin/orders/{$order->uuid}", ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
    }

    public function test_cancelling_a_paid_order_refunds_the_captured_payment(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->for($vendor)->create(['stock' => 10]);
        $order = Order::factory()->status(OrderStatus::Paid)->create(['total' => '40.00']);
        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'quantity' => 1,
            'status' => FulfillmentStatus::Pending,
        ]);
        // The capture that cancellation must reverse.
        Payment::factory()->create([
            'order_id' => $order->id,
            'idempotency_key' => 'order_'.$order->uuid,
            'reference' => 'txn_original',
            'status' => PaymentStatus::Succeeded,
            'amount' => '40.00',
        ]);

        $this->actAsAdmin();

        $this->patchJson("/api/admin/orders/{$order->uuid}", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(
            1,
            Payment::where('order_id', $order->id)->where('status', PaymentStatus::Refunded)->count(),
        );
    }

    public function test_cancelling_a_pending_order_does_not_refund(): void
    {
        // A pending order was never captured, so there is nothing to reverse.
        $order = Order::factory()->status(OrderStatus::Pending)->create(['total' => '40.00']);

        $this->actAsAdmin();

        $this->patchJson("/api/admin/orders/{$order->uuid}", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(0, Payment::where('status', PaymentStatus::Refunded)->count());
    }
}
