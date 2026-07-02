<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks in the API_CONTRACT.md fix: the public surface is uuid-only, but
 * `PUT /cart` (`product_id`) and `POST /orders` (`address_id`) still validated
 * against the internal bigint id, so the storefront (which only ever holds
 * uuids) could never build a valid request. Cart/order resolution must accept
 * the uuid and resolve the model server-side; the bigint must be rejected.
 */
class CartCheckoutUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_accepts_the_product_uuid_and_resolves_it_server_side(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 2,
        ])->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_cart_rejects_the_internal_bigint_product_id(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_cart_show_reports_the_product_uuid_not_the_internal_id(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 1,
        ])->assertOk();

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('items.0.product_id', $product->uuid);
    }

    public function test_cart_item_can_be_removed_by_product_uuid(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 1,
        ])->assertOk();

        $this->deleteJson("/api/cart/{$product->uuid}")->assertOk();

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_checkout_accepts_the_address_uuid_and_resolves_it_server_side(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $address = Address::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $address->uuid,
        ])->assertCreated();
    }

    public function test_checkout_rejects_the_internal_bigint_address_id(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $address = Address::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $address->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['address_id']);
    }

    public function test_checkout_still_rejects_another_customers_address_uuid(): void
    {
        $user = User::factory()->create();
        $otherUsersAddress = Address::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        Sanctum::actingAs($user);

        $this->putJson('/api/cart', [
            'product_id' => $product->uuid,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $otherUsersAddress->uuid,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['address_id']);
    }
}
