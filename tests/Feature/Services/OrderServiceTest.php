<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OutOfStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(OrderService::class);
    }

    public function test_it_places_an_order_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(10)->create(['price' => '12.50']);
        $this->seedCart($user, $product, quantity: 2);

        $order = $this->service->placeFromCart($user, PaymentMethod::Card);

        // Order persisted and paid.
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('25.00', $order->total);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);

        // Stock moved by exactly the ordered quantity.
        $this->assertSame(8, $product->refresh()->stock);

        // Cart emptied so the order can't be double-submitted.
        $this->assertSame(0, $user->cart->items()->count());
    }

    public function test_it_throws_and_rolls_back_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(1)->create();
        $this->seedCart($user, $product, quantity: 5);

        try {
            $this->service->placeFromCart($user, PaymentMethod::Card);
            $this->fail('Expected OutOfStockException was not thrown.');
        } catch (OutOfStockException $e) {
            $this->assertSame($product->id, $e->product?->id);
        }

        // Nothing committed: no order, stock untouched, cart intact.
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->refresh()->stock);
        $this->assertSame(1, $user->cart->items()->count());
    }

    private function seedCart(User $user, Product $product, int $quantity): void
    {
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }
}
