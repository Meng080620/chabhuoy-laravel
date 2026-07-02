<?php

namespace Tests\Feature\Api;

use App\Models\BrandStore;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBrandStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_non_admin_cannot_manage_brand_stores(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->postJson('/api/admin/brand-stores', ['name' => 'Hijack'])
            ->assertForbidden();

        $this->assertDatabaseMissing('brand_stores', ['name' => 'Hijack']);
    }

    public function test_admin_can_list_all_brand_stores_ordered_by_position(): void
    {
        BrandStore::factory()->create(['name' => 'Second', 'position' => 2]);
        BrandStore::factory()->create(['name' => 'First', 'position' => 1]);
        BrandStore::factory()->inactive()->create(['name' => 'Hidden', 'position' => 3]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/brand-stores')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'First')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'caption', 'logo_url', 'link_url', 'position', 'is_active']],
            ]);
    }

    public function test_admin_can_create_a_brand_store_with_a_logo(): void
    {
        Storage::fake('public');
        $this->actAsAdmin();

        $response = $this->postJson('/api/admin/brand-stores', [
            'name' => 'Adidas',
            'caption' => 'Official store',
            'link_url' => 'https://example.com/adidas',
            'position' => 1,
            'logo' => UploadedFile::fake()->image('adidas.png', 200, 200),
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Adidas');

        $path = BrandStore::firstOrFail()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('http', $response->json('data.logo_url'));
    }

    public function test_creating_a_brand_store_requires_a_name(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/brand-stores', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');
        $this->actAsAdmin();

        $this->postJson('/api/admin/brand-stores', [
            'name' => 'Bad',
            'logo' => UploadedFile::fake()->create('x.pdf', 50, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    }

    public function test_admin_can_update_and_replace_the_logo(): void
    {
        Storage::fake('public');
        $store = BrandStore::factory()->create([
            'name' => 'Old',
            'logo_path' => UploadedFile::fake()->image('old.png')->store('brand-stores', 'public'),
        ]);
        $oldPath = $store->logo_path;
        $this->actAsAdmin();

        $this->putJson("/api/admin/brand-stores/{$store->id}", [
            'name' => 'New',
            'is_active' => false,
            'logo' => UploadedFile::fake()->image('new.png'),
        ])->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.is_active', false);

        $store->refresh();
        $this->assertNotSame($oldPath, $store->logo_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($store->logo_path);
    }

    public function test_admin_can_delete_a_brand_store_and_its_logo(): void
    {
        Storage::fake('public');
        $store = BrandStore::factory()->create([
            'logo_path' => UploadedFile::fake()->image('gone.png')->store('brand-stores', 'public'),
        ]);
        $path = $store->logo_path;
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/brand-stores/{$store->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('brand_stores', ['id' => $store->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
