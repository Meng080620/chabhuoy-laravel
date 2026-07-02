<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBannerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_non_admin_cannot_manage_banners(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->postJson('/api/admin/banners', ['type' => 'hero', 'title' => 'Hijack'])
            ->assertForbidden();

        $this->assertDatabaseMissing('banners', ['title' => 'Hijack']);
    }

    public function test_admin_can_list_all_banners_ordered_by_position(): void
    {
        Banner::factory()->create(['title' => 'Second', 'position' => 2]);
        Banner::factory()->create(['title' => 'First', 'position' => 1]);
        Banner::factory()->inactive()->create(['title' => 'Hidden', 'position' => 3]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/banners')
            ->assertOk()
            ->assertJsonCount(3, 'data') // admin sees inactive too
            ->assertJsonPath('data.0.title', 'First')
            ->assertJsonPath('data.1.title', 'Second')
            ->assertJsonStructure([
                'data' => [['id', 'type', 'title', 'subtitle', 'image_url', 'link_url', 'cta_label', 'position', 'is_active']],
            ]);
    }

    public function test_admin_can_create_a_banner_with_an_uploaded_image(): void
    {
        Storage::fake('public');
        $this->actAsAdmin();

        $response = $this->postJson('/api/admin/banners', [
            'type' => 'hero',
            'title' => 'Ramadan Sale',
            'subtitle' => 'Up to 50% off',
            'cta_label' => 'Shop now',
            'link_url' => 'https://example.com/ramadan',
            'position' => 1,
            'image' => UploadedFile::fake()->image('hero.jpg', 1200, 400),
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Ramadan Sale')
            ->assertJsonPath('data.type', 'hero');

        $path = Banner::firstOrFail()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        // Resource exposes a usable absolute URL, never the raw storage path.
        $this->assertStringContainsString($path, $response->json('data.image_url'));
        $this->assertStringStartsWith('http', $response->json('data.image_url'));
    }

    public function test_creating_a_banner_requires_a_valid_type_and_title(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/banners', ['type' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'title']);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $this->actAsAdmin();

        $this->postJson('/api/admin/banners', [
            'type' => 'hero',
            'title' => 'Bad file',
            'image' => UploadedFile::fake()->create('malware.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_admin_can_update_a_banner_and_replace_its_image(): void
    {
        Storage::fake('public');
        $banner = Banner::factory()->create([
            'title' => 'Old',
            'image_path' => UploadedFile::fake()->image('old.jpg')->store('banners', 'public'),
        ]);
        $oldPath = $banner->image_path;
        $this->actAsAdmin();

        $this->putJson("/api/admin/banners/{$banner->id}", [
            'type' => 'promo',
            'title' => 'New',
            'position' => 5,
            'is_active' => false,
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.type', 'promo')
            ->assertJsonPath('data.is_active', false);

        $banner->refresh();
        $this->assertNotSame($oldPath, $banner->image_path);
        // Replacing the image cleans up the previous file — no orphan storage.
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($banner->image_path);
    }

    public function test_admin_can_delete_a_banner_and_its_image(): void
    {
        Storage::fake('public');
        $banner = Banner::factory()->create([
            'image_path' => UploadedFile::fake()->image('gone.jpg')->store('banners', 'public'),
        ]);
        $path = $banner->image_path;
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/banners/{$banner->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
