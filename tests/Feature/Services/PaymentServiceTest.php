<?php

namespace Tests\Feature\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentFailedException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Contracts\PaymentGateway;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PaymentService::class);
    }

    public function test_charging_the_same_order_twice_captures_only_once(): void
    {
        $order = Order::factory()->create(['total' => '50.00']);

        $first = $this->service->charge($order, PaymentMethod::Card);
        $second = $this->service->charge($order, PaymentMethod::Card);

        // The retry replays the original reference instead of re-charging.
        $this->assertNotNull($first);
        $this->assertSame($first, $second);

        // Exactly one ledger row — no double capture.
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_distinct_orders_get_distinct_payments(): void
    {
        $a = Order::factory()->create(['total' => '10.00']);
        $b = Order::factory()->create(['total' => '20.00']);

        $refA = $this->service->charge($a, PaymentMethod::Card);
        $refB = $this->service->charge($b, PaymentMethod::Card);

        $this->assertNotSame($refA, $refB);
        $this->assertSame(2, Payment::count());
    }

    public function test_cod_captures_nothing_at_checkout(): void
    {
        $order = Order::factory()->create(['total' => '30.00']);

        $this->assertNull($this->service->charge($order, PaymentMethod::Cod));
        $this->assertSame(0, Payment::count());
    }

    public function test_a_prior_failed_attempt_is_not_silently_retried(): void
    {
        $order = Order::factory()->create(['total' => '40.00']);

        // A failed payment already exists for this order's idempotency key.
        Payment::factory()->failed()->create([
            'order_id' => $order->id,
            'idempotency_key' => 'order_'.$order->uuid,
            'amount' => '40.00',
        ]);

        $this->expectException(PaymentFailedException::class);
        $this->service->charge($order, PaymentMethod::Card);
    }

    public function test_zero_total_is_rejected(): void
    {
        $order = Order::factory()->create(['total' => '0.00']);

        $this->expectException(PaymentFailedException::class);
        $this->service->charge($order, PaymentMethod::Card);
    }

    public function test_checkout_records_a_succeeded_payment(): void
    {
        // End-to-end: placing an order through the full service leaves a ledger row.
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '15.00']);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $order = $this->app->make(OrderService::class)
            ->placeFromCart($user, PaymentMethod::Card, Address::factory()->create(['user_id' => $user->id]));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'idempotency_key' => 'order_'.$order->uuid,
            'status' => PaymentStatus::Succeeded->value,
            'amount' => '30.00',
        ]);
    }

    public function test_refunding_a_captured_order_records_a_refund_ledger_row(): void
    {
        $order = Order::factory()->create(['total' => '50.00']);
        $this->service->charge($order, PaymentMethod::Card);

        $refund = $this->service->refund($order);

        $this->assertNotNull($refund);
        $this->assertSame(PaymentStatus::Refunded, $refund->status);
        $this->assertSame('50.00', (string) $refund->amount);
        $this->assertStringStartsWith('rfnd_', (string) $refund->reference);
        // The original capture is left intact — the refund is its own ledger row.
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', PaymentStatus::Succeeded)->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', PaymentStatus::Refunded)->count());
    }

    public function test_refunds_are_idempotent(): void
    {
        $order = Order::factory()->create(['total' => '50.00']);
        $this->service->charge($order, PaymentMethod::Card);

        $first = $this->service->refund($order);
        $second = $this->service->refund($order);

        // The retry replays the original refund instead of reversing twice.
        $this->assertSame($first?->reference, $second?->reference);
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', PaymentStatus::Refunded)->count());
    }

    public function test_refunding_an_uncaptured_order_is_a_noop(): void
    {
        // COD / never captured — there is nothing to reverse.
        $order = Order::factory()->create(['total' => '30.00']);

        $this->assertNull($this->service->refund($order));
        $this->assertSame(0, Payment::where('status', PaymentStatus::Refunded)->count());
    }

    public function test_a_gateway_decline_records_a_failed_payment_and_throws(): void
    {
        // Swap in a gateway that always declines to prove the failure path.
        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function charge(string $amount, string $idempotencyKey): string
            {
                throw new PaymentFailedException('Card declined.');
            }

            public function refund(string $amount, string $idempotencyKey, string $originalReference): string
            {
                return 'rfnd_unused';
            }
        });
        $service = $this->app->make(PaymentService::class);
        $order = Order::factory()->create(['total' => '25.00']);

        try {
            $service->charge($order, PaymentMethod::Card);
            $this->fail('Expected PaymentFailedException.');
        } catch (PaymentFailedException) {
            // expected
        }

        // A Failed row is recorded (so a retry isn't silently re-attempted) and
        // nothing was captured.
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', PaymentStatus::Failed)->count());
        $this->assertSame(0, Payment::where('status', PaymentStatus::Succeeded)->count());
    }
}
