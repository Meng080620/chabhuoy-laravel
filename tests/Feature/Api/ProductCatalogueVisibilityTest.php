<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogueVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_vendor_products_appear_in_the_catalogue(): void
    {
        $product = Product::factory()->create(); // vendor active by default

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->uuid);
    }

    public function test_suspended_vendor_products_are_hidden_from_the_listing(): void
    {
        $suspended = Vendor::factory()->suspended()->create();
        Product::factory()->for($suspended)->create();

        $active = Vendor::factory()->create();
        $visible = Product::factory()->for($active)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->uuid);
    }

    public function test_suspended_vendor_product_404s_on_direct_access(): void
    {
        $suspended = Vendor::factory()->suspended()->create();
        $product = Product::factory()->for($suspended)->create();

        $this->getJson("/api/products/{$product->uuid}")
            ->assertNotFound();
    }

    public function test_inactive_product_of_an_active_vendor_is_still_hidden(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$product->uuid}")->assertNotFound();
    }
}
