<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_top_level_categories_with_nested_children(): void
    {
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        $phones = Category::factory()->create(['name' => 'Phones', 'parent_id' => $electronics->id]);
        Category::factory()->create(['name' => 'Apparel']);

        $response = $this->getJson('/api/categories')->assertOk();

        // Two top-level entries (Apparel, Electronics) — the child is NOT one.
        $response->assertJsonCount(2, 'data')
            // Ordered by name: Apparel before Electronics.
            ->assertJsonPath('data.0.name', 'Apparel')
            ->assertJsonPath('data.1.name', 'Electronics')
            // The child is nested under its parent.
            ->assertJsonCount(1, 'data.1.children')
            ->assertJsonPath('data.1.children.0.slug', $phones->slug);
    }

    public function test_show_resolves_a_category_by_slug(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Laptops', 'parent_id' => $category->id]);

        $this->getJson("/api/categories/{$category->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $category->slug)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonCount(1, 'data.children');
    }

    public function test_unknown_slug_404s(): void
    {
        $this->getJson('/api/categories/does-not-exist')->assertNotFound();
    }

    public function test_browsing_categories_needs_no_authentication(): void
    {
        Category::factory()->create();

        // No Authorization header — the catalogue taxonomy is public.
        $this->getJson('/api/categories')->assertOk();
    }
}
