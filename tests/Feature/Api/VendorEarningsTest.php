<?php

namespace Tests\Feature\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorEarningsTest extends TestCase
{
    use RefreshDatabase;

    private function actAsVendor(Vendor $vendor): void
    {
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());
    }

    /**
     * A Shipped line on an order, ready to be delivered through the API.
     *
     * @return array{0: Order, 1: OrderItem}
     */
    private function shippedLineFor(Vendor $vendor, string $lineTotal = '20.00'): array
    {
        $order = Order::factory()->status(OrderStatus::Shipped)->create();

        $line = OrderItem::factory()->forVendor($vendor)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'unit_price' => '10.00',
            'quantity' => 2,
            'line_total' => $lineTotal,
        ]);

        return [$order, $line];
    }

    private function deliver(Order $order): TestResponse
    {
        return $this->patchJson("/api/vendor/orders/{$order->uuid}", [
            'status' => FulfillmentStatus::Delivered->value,
        ]);
    }

    public function test_delivering_a_line_credits_the_vendor_payout_balance(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => 0]);
        [$order] = $this->shippedLineFor($vendor, '20.00');

        $this->actAsVendor($vendor);
        $this->deliver($order)->assertOk();

        // Vendor earns the full line_total of the delivered line.
        $this->assertSame('20.00', $vendor->refresh()->payout_balance);
    }

    public function test_redelivering_an_already_delivered_line_does_not_double_credit(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => 0]);
        [$order] = $this->shippedLineFor($vendor, '20.00');

        $this->actAsVendor($vendor);
        $this->deliver($order)->assertOk();
        // Idempotent: the line is already Delivered, so a repeat call is a no-op.
        $this->deliver($order)->assertOk();

        $this->assertSame('20.00', $vendor->refresh()->payout_balance);
    }

    public function test_on_a_shared_order_only_the_delivering_vendor_is_credited(): void
    {
        $vendorA = Vendor::factory()->create(['payout_balance' => 0]);
        $vendorB = Vendor::factory()->create(['payout_balance' => 0]);

        $order = Order::factory()->status(OrderStatus::Shipped)->create();
        OrderItem::factory()->forVendor($vendorA)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'line_total' => '20.00',
        ]);
        OrderItem::factory()->forVendor($vendorB)->create([
            'order_id' => $order->id,
            'status' => FulfillmentStatus::Shipped,
            'line_total' => '30.00',
        ]);

        $this->actAsVendor($vendorA);
        $this->deliver($order)->assertOk();

        $this->assertSame('20.00', $vendorA->refresh()->payout_balance);
        // B's line was untouched, so B earns nothing.
        $this->assertSame('0.00', $vendorB->refresh()->payout_balance);
    }

    public function test_earnings_endpoint_returns_balance_and_payout_history(): void
    {
        $vendor = Vendor::factory()->create(['payout_balance' => '20.00']);
        Payout::factory()->for($vendor)->create([
            'amount' => '5.00',
            'status' => PayoutStatus::Completed,
        ]);

        $this->actAsVendor($vendor);

        $this->getJson('/api/vendor/earnings')
            ->assertOk()
            ->assertJsonPath('data.available_balance', '20.00')
            ->assertJsonPath('data.total_paid_out', '5.00')
            ->assertJsonCount(1, 'data.recent_payouts')
            ->assertJsonStructure([
                'data' => [
                    'available_balance',
                    'total_paid_out',
                    'recent_payouts' => [['id', 'amount', 'status', 'processed_at', 'created_at']],
                ],
            ]);
    }

    public function test_earnings_only_reflects_the_authed_vendors_own_money(): void
    {
        $me = Vendor::factory()->create(['payout_balance' => '20.00']);
        $other = Vendor::factory()->create(['payout_balance' => '999.00']);
        Payout::factory()->for($other)->create(['amount' => '777.00', 'status' => PayoutStatus::Completed]);

        $this->actAsVendor($me);

        $this->getJson('/api/vendor/earnings')
            ->assertOk()
            ->assertJsonPath('data.available_balance', '20.00')
            // Another vendor's completed payouts must not leak into my totals.
            ->assertJsonPath('data.total_paid_out', '0.00')
            ->assertJsonCount(0, 'data.recent_payouts');
    }

    public function test_a_non_vendor_cannot_access_vendor_earnings(): void
    {
        $customer = User::factory()->create();
        Sanctum::actingAs($customer, $customer->role->abilities());

        $this->getJson('/api/vendor/earnings')->assertForbidden();
    }
}
