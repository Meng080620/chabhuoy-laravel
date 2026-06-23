<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** Count the SQL queries issued while running $fn. */
    private function queriesDuring(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_public_list_includes_category_and_vendor(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.category.name', $product->category->name)
            ->assertJsonPath('data.0.vendor.name', $product->vendor->name);
    }

    public function test_public_list_query_count_is_constant_regardless_of_size(): void
    {
        // All pinned to one active vendor; categories vary (factory default).
        $vendor = Vendor::factory()->create();

        Product::factory()->for($vendor)->count(1)->create();
        $withOne = $this->queriesDuring(fn () => $this->getJson('/api/products')->assertOk());

        Product::factory()->for($vendor)->count(19)->create(); // 20 total
        $withTwenty = $this->queriesDuring(fn () => $this->getJson('/api/products')->assertOk());

        // Eager loading makes this O(1) in queries. If it grows with rows, a
        // relation is being lazy-loaded per item — an N+1 regression.
        $this->assertSame(
            $withOne,
            $withTwenty,
            "Query count grew from {$withOne} to {$withTwenty} as rows scaled — N+1 regression.",
        );
    }

    public function test_vendor_list_query_count_is_constant(): void
    {
        $vendor = Vendor::factory()->create();
        $abilities = $vendor->user->role->abilities();

        // Re-act with a FRESH user instance before each measurement. Sanctum's
        // actingAs reuses one in-memory user, so a relation lazy-loaded in the
        // first request would stay cached for the second and understate its
        // cost — fresh() gives each request a cold cache, like production.
        Product::factory()->for($vendor)->count(1)->create();
        Sanctum::actingAs($vendor->user->fresh(), $abilities);
        $withOne = $this->queriesDuring(fn () => $this->getJson('/api/vendor/products')->assertOk());

        Product::factory()->for($vendor)->count(19)->create();
        Sanctum::actingAs($vendor->user->fresh(), $abilities);
        $withTwenty = $this->queriesDuring(fn () => $this->getJson('/api/vendor/products')->assertOk());

        $this->assertSame(
            $withOne,
            $withTwenty,
            "Query count grew from {$withOne} to {$withTwenty} as rows scaled — N+1 regression.",
        );
    }
}
