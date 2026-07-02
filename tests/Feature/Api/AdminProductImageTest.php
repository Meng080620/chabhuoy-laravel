<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_product_resource_exposes_a_null_image_url_by_default(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_url', null);
    }

    public function test_a_non_admin_cannot_upload_a_product_image(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->postJson("/api/admin/products/{$product->uuid}/image", [
            'image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertForbidden();
    }

    public function test_admin_can_upload_a_product_image_and_it_shows_on_the_public_resource(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->actAsAdmin();

        $response = $this->postJson("/api/admin/products/{$product->uuid}/image", [
            'image' => UploadedFile::fake()->image('shoe.jpg', 600, 600),
        ])->assertOk()
            ->assertJsonPath('data.id', $product->uuid);

        $path = $product->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('http', $response->json('data.image_url'));

        // Public storefront now serves the uploaded image.
        $this->getJson("/api/products/{$product->uuid}")
            ->assertJsonPath('data.image_url', $response->json('data.image_url'));
    }

    public function test_uploading_a_new_image_replaces_the_old_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create([
            'image_path' => UploadedFile::fake()->image('old.jpg')->store('products', 'public'),
        ]);
        $oldPath = $product->image_path;
        $this->actAsAdmin();

        $this->postJson("/api/admin/products/{$product->uuid}/image", [
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_image_is_required_and_must_be_an_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->actAsAdmin();

        $this->postJson("/api/admin/products/{$product->uuid}/image", [
            'image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->postJson("/api/admin/products/{$product->uuid}/image", [])
            ->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_admin_can_remove_a_product_image(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create([
            'image_path' => UploadedFile::fake()->image('gone.jpg')->store('products', 'public'),
        ]);
        $path = $product->image_path;
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/products/{$product->uuid}/image")
            ->assertOk()
            ->assertJsonPath('data.image_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($product->fresh()->image_path);
    }
}
