<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_non_admin_cannot_manage_categories(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->postJson('/api/admin/categories', ['name' => 'Hijack'])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Hijack']);
    }

    public function test_admin_can_list_the_category_tree(): void
    {
        $parent = Category::factory()->create(['name' => 'Textiles']);
        Category::factory()->create(['name' => 'Scarves', 'parent_id' => $parent->id]);
        Category::factory()->create(['name' => 'Pottery']); // another top-level

        $this->actAsAdmin();

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')                 // top-level only (Pottery, Textiles by name)
            ->assertJsonPath('data.1.name', 'Textiles')
            ->assertJsonCount(1, 'data.1.children')
            ->assertJsonPath('data.1.children.0.name', 'Scarves')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'children']]]);
    }

    public function test_admin_can_create_a_category_and_slug_is_auto_generated(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/categories', ['name' => 'Khmer Silk'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Khmer Silk')
            ->assertJsonPath('data.slug', 'khmer-silk');

        $this->assertDatabaseHas('categories', [
            'name' => 'Khmer Silk',
            'slug' => 'khmer-silk',
            'parent_id' => null,
        ]);
    }

    public function test_slug_collisions_are_disambiguated(): void
    {
        Category::factory()->create(['name' => 'Silk', 'slug' => 'silk']);
        $this->actAsAdmin();

        // A different name that slugifies to the same base must not 500 on the
        // unique index — it gets a suffixed slug instead.
        $this->postJson('/api/admin/categories', ['name' => 'Silk!'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'silk-2');
    }

    public function test_creating_a_category_requires_a_unique_name(): void
    {
        Category::factory()->create(['name' => 'Textiles']);
        $this->actAsAdmin();

        $this->postJson('/api/admin/categories', ['name' => 'Textiles'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_child_must_reference_an_existing_parent(): void
    {
        $this->actAsAdmin();

        $this->postJson('/api/admin/categories', [
            'name' => 'Orphan',
            'parent_id' => 99999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_admin_can_rename_a_category_and_the_slug_follows(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
        $this->actAsAdmin();

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'new-name');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'new-name']);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();
        $this->actAsAdmin();

        $this->putJson("/api/admin/categories/{$category->id}", [
            'name' => $category->name,
            'parent_id' => $category->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_deleting_a_category_with_products_is_blocked(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_deleting_a_category_with_children_is_blocked(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/categories/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_admin_can_delete_an_empty_category(): void
    {
        $category = Category::factory()->create();
        $this->actAsAdmin();

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
