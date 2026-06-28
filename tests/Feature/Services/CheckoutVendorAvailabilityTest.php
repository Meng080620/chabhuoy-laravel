<?php

namespace Tests\Feature\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\VendorUnavailableException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutVendorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(OrderService::class);
    }

    public function test_checkout_rejects_a_suspended_vendors_product_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->suspended()->create();
        $product = Product::factory()->for($vendor)->withStock(10)->create();
        $this->seedCart($user, $product, quantity: 1);

        try {
            $this->service->placeFromCart($user, PaymentMethod::Card, Address::factory()->create(['user_id' => $user->id]));
            $this->fail('Expected VendorUnavailableException was not thrown.');
        } catch (VendorUnavailableException $e) {
            $this->assertSame($product->id, $e->product?->id);
        }

        // Nothing committed: no order, stock untouched, cart intact.
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame(1, $user->cart->items()->count());
    }

    public function test_checkout_rejects_a_deactivated_listing_from_an_active_vendor(): void
    {
        $user = User::factory()->create();
        // Active vendor, but the listing itself was switched off.
        $product = Product::factory()->withStock(5)->create(['is_active' => false]);
        $this->seedCart($user, $product, quantity: 1);

        $this->expectException(VendorUnavailableException::class);

        $this->service->placeFromCart($user, PaymentMethod::Card, Address::factory()->create(['user_id' => $user->id]));
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
