<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardPerformanceTest extends TestCase
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

    /** Seed one self-contained "unit" of dashboard data (orders, items, product, vendor). */
    private function seedUnit(): void
    {
        $buyer = User::factory()->create();
        $vendor = Vendor::factory()->create(['payout_balance' => '10.00']);
        $product = Product::factory()->for($vendor)->withStock(1)->create();

        Order::factory()->for($buyer)->status(OrderStatus::Delivered)->create(['placed_at' => now()]);
        Order::factory()->for($buyer)->status(OrderStatus::Pending)->create(['placed_at' => now()]);
        OrderItem::factory()->for($product)->for($vendor)->create();
    }

    public function test_dashboard_query_count_is_constant_regardless_of_data_size(): void
    {
        $admin = User::factory()->admin()->create();
        $abilities = $admin->role->abilities();

        // Every KPI is a SQL aggregate — the query count must NOT grow with rows.
        // A fresh acting user before each measurement gives each request a cold
        // in-memory cache, matching production (mirrors ProductListPerformanceTest).
        $this->seedUnit();
        Sanctum::actingAs($admin->fresh(), $abilities);
        $withOne = $this->queriesDuring(fn () => $this->getJson('/api/admin/dashboard')->assertOk());

        collect(range(1, 15))->each(fn () => $this->seedUnit());
        Sanctum::actingAs($admin->fresh(), $abilities);
        $withMany = $this->queriesDuring(fn () => $this->getJson('/api/admin/dashboard')->assertOk());

        $this->assertSame(
            $withOne,
            $withMany,
            "Dashboard query count grew from {$withOne} to {$withMany} as data scaled — an aggregate was turned into a per-row fan-out.",
        );
    }
}
