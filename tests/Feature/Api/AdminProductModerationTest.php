<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductModerationTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_non_admin_cannot_list_products(): void
    {
        Product::factory()->count(2)->create();

        // A vendor (non-admin) trying to reach the moderation list.
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_admin_sees_products_across_all_vendors(): void
    {
        Product::factory()->for(Vendor::factory())->create();
        Product::factory()->for(Vendor::factory())->create();
        $this->actAsAdmin();

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'is_active', 'vendor', 'category']],
                'links',
                'meta',
            ]);
    }

    public function test_admin_can_filter_products_by_status(): void
    {
        Product::factory()->count(2)->create(); // active
        Product::factory()->count(3)->create(['is_active' => false]);
        $this->actAsAdmin();

        $this->getJson('/api/admin/products?status=inactive')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/admin/products?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_filter_products_by_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        Product::factory()->for($vendor)->count(2)->create();
        Product::factory()->create(); // another vendor
        $this->actAsAdmin();

        $this->getJson('/api/admin/products?vendor_id='.$vendor->uuid)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_search_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Khmer Silk Scarf']);
        Product::factory()->create(['name' => 'Plastic Bucket']);
        $this->actAsAdmin();

        $this->getJson('/api/admin/products?search=silk')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Khmer Silk Scarf');
    }

    public function test_admin_can_toggle_a_product_active_flag(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $this->actAsAdmin();

        $this->patchJson("/api/admin/products/{$product->uuid}", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($product->refresh()->is_active);
    }

    public function test_patch_requires_a_boolean_is_active(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $this->actAsAdmin();

        $this->patchJson("/api/admin/products/{$product->uuid}", [
            'is_active' => 'maybe',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->assertTrue($product->refresh()->is_active);
    }

    public function test_a_non_admin_cannot_toggle_a_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->patchJson("/api/admin/products/{$product->uuid}", [
            'is_active' => false,
        ])->assertForbidden();

        $this->assertTrue($product->refresh()->is_active);
    }
}
