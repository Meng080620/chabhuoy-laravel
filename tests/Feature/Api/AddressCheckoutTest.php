<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_the_chosen_address_onto_the_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $this->seedCart($user, $product, quantity: 1);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Jane Buyer',
            'phone' => '012345678',
            'line1' => '42 Norodom Blvd',
            'city' => 'Phnom Penh',
            'postal_code' => '12000',
            'country' => 'KH',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $address->uuid,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.shipping.recipient_name', 'Jane Buyer')
            ->assertJsonPath('data.shipping.city', 'Phnom Penh');

        // The destination is frozen onto the order as an immutable snapshot,
        // independent of any later edit to the saved address.
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'ship_recipient_name' => 'Jane Buyer',
            'ship_line1' => '42 Norodom Blvd',
            'ship_city' => 'Phnom Penh',
            'ship_postal_code' => '12000',
            'ship_country' => 'KH',
        ]);
    }

    public function test_editing_the_address_after_checkout_does_not_rewrite_the_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $this->seedCart($user, $product, quantity: 1);
        $address = Address::factory()->create(['user_id' => $user->id, 'city' => 'Phnom Penh']);

        Sanctum::actingAs($user);
        $this->postJson('/api/orders', ['payment_method' => 'card', 'address_id' => $address->uuid])
            ->assertCreated();

        $address->update(['city' => 'Siem Reap']);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'ship_city' => 'Phnom Penh']);
    }

    public function test_checkout_rejects_another_users_address(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $this->seedCart($buyer, $product, quantity: 1);

        $someoneElse = Address::factory()->create(); // belongs to a different user

        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders', [
            'payment_method' => 'card',
            'address_id' => $someoneElse->uuid,
        ])->assertStatus(422);

        // Nothing was placed.
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_an_address(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withStock(5)->create(['price' => '10.00']);
        $this->seedCart($user, $product, quantity: 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', ['payment_method' => 'card'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('address_id');
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
