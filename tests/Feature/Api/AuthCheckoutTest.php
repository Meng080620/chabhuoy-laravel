<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register_browse_and_check_out(): void
    {
        // A vendor with a product to buy.
        $product = Product::factory()->withStock(5)->create(['price' => '20.00']);

        // 1. Register -> issues a token.
        $register = $this->postJson('/api/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token'])
            ->assertJsonPath('user.role', 'customer');

        $token = $register->json('token');
        $auth = ['Authorization' => "Bearer {$token}"];

        // 2. Public catalogue lists the active product.
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->uuid);

        // 3. Add to cart (absolute quantity).
        $this->putJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ], $auth)->assertOk();

        // 4. Add a shipping address (first one becomes the default).
        $this->postJson('/api/addresses', [
            'recipient_name' => 'Jane Buyer',
            'phone' => '012345678',
            'line1' => '42 Norodom Blvd',
            'city' => 'Phnom Penh',
            'postal_code' => '12000',
            'country' => 'KH',
        ], $auth)->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $address = User::where('email', 'jane@example.com')->firstOrFail()
            ->addresses()->firstOrFail();

        // 5. Check out against that address.
        $checkout = $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $address->id,
        ], $auth);

        // 201: a resource backed by a just-created model reports Created.
        $checkout->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Paid->value)
            ->assertJsonPath('data.total', '40.00');

        // Server-side effects: order persisted, stock reserved, cart cleared.
        $this->assertDatabaseHas('orders', ['total' => '40.00', 'status' => 'paid']);
        $this->assertSame(3, $product->refresh()->stock);

        $customer = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame(0, $customer->cart->items()->count());
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'real@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'real@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'valid@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'token'])
            ->assertJsonPath('user.email', 'valid@example.com');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'brute@example.com',
            'password' => 'password123',
        ]);

        // The named 'login' limiter allows 5 attempts per minute, per email+IP.
        // The first 5 bad attempts are rejected as invalid credentials (422);
        // the 6th is blocked by the throttle before reaching the controller.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'brute@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'brute@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_guest_cannot_place_an_order(): void
    {
        $this->postJson('/api/orders', ['payment_method' => 'card'])
            ->assertUnauthorized();
    }

    public function test_customer_is_forbidden_from_vendor_and_admin_areas(): void
    {
        Sanctum::actingAs(User::factory()->create()); // default role: customer

        $this->getJson('/api/vendor/products')->assertForbidden();
        $this->getJson('/api/admin/reports/sales')->assertForbidden();
    }

    public function test_active_vendor_can_create_a_product(): void
    {
        $vendor = Vendor::factory()->create();
        // Mirror the abilities a real vendor login mints, so the route's
        // `abilities:vendor:manage` gate is exercised as it is in production.
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $category = \App\Models\Category::factory()->create();

        $this->postJson('/api/vendor/products', [
            'name' => 'Hand-roasted Coffee',
            'category_id' => $category->id,
            'price' => '9.50',
            'stock' => 30,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Hand-roasted Coffee');

        $this->assertDatabaseHas('products', [
            'vendor_id' => $vendor->id,
            'name' => 'Hand-roasted Coffee',
        ]);
    }

    public function test_suspended_vendor_cannot_reach_vendor_area(): void
    {
        $vendor = Vendor::factory()->suspended()->create();
        Sanctum::actingAs($vendor->user);

        $this->getJson('/api/vendor/products')->assertForbidden();
    }
}
