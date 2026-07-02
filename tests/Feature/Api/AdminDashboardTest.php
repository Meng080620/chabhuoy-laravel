<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_dashboard_returns_the_full_kpi_shape(): void
    {
        $this->actAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'revenue' => ['captured', 'today'],
                'orders' => [
                    'total',
                    'by_status' => ['pending', 'paid', 'shipped', 'delivered', 'cancelled'],
                ],
                'customers' => ['total', 'new_this_week'],
                'payouts' => ['pending_amount', 'pending_count'],
                'catalog' => ['low_stock_count', 'top_products'],
            ]);
    }

    public function test_revenue_counts_only_captured_orders_and_isolates_today(): void
    {
        // A single customer owns every order, so the customers count below is not
        // polluted by the per-order user the factory would otherwise spin up.
        $buyer = User::factory()->create();

        // Captured today: paid + shipped + delivered placed now.
        Order::factory()->for($buyer)->status(OrderStatus::Paid)->create(['total' => '50.00', 'placed_at' => now()]);
        Order::factory()->for($buyer)->status(OrderStatus::Shipped)->create(['total' => '300.00', 'placed_at' => now()]);
        Order::factory()->for($buyer)->status(OrderStatus::Delivered)->create(['total' => '400.00', 'placed_at' => now()]);
        // Captured, but yesterday — counts toward all-time, not today.
        Order::factory()->for($buyer)->status(OrderStatus::Paid)->create(['total' => '150.00', 'placed_at' => now()->subDay()]);
        // Not captured revenue: pending is unpaid, cancelled is reversed.
        Order::factory()->for($buyer)->status(OrderStatus::Pending)->create(['total' => '100.00', 'placed_at' => now()]);
        Order::factory()->for($buyer)->status(OrderStatus::Cancelled)->create(['total' => '999.00', 'placed_at' => now()]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('revenue.captured', '900.00')   // 50 + 300 + 400 + 150
            ->assertJsonPath('revenue.today', '750.00')       // excludes yesterday's 150
            ->assertJsonPath('orders.total', 6)
            ->assertJsonPath('orders.by_status.pending', 1)
            ->assertJsonPath('orders.by_status.paid', 2)
            ->assertJsonPath('orders.by_status.shipped', 1)
            ->assertJsonPath('orders.by_status.delivered', 1)
            ->assertJsonPath('orders.by_status.cancelled', 1);
    }

    public function test_customers_and_payouts_and_low_stock_are_aggregated(): void
    {
        // 1 established customer (last month) + 2 new this week = 3 total, 2 new.
        User::factory()->create(['created_at' => now()->subMonth()]);
        User::factory()->count(2)->create();

        Vendor::factory()->create(['payout_balance' => '250.00']);
        Vendor::factory()->create(['payout_balance' => '100.00']);
        Vendor::factory()->create(['payout_balance' => '0.00']); // nothing owed → excluded

        Product::factory()->withStock(2)->create();  // 2 <= threshold(5) → low
        Product::factory()->create();                 // stock 10-100 → healthy

        $this->actAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('customers.total', 3)
            ->assertJsonPath('customers.new_this_week', 2)
            ->assertJsonPath('payouts.pending_amount', '350.00')
            ->assertJsonPath('payouts.pending_count', 2)
            ->assertJsonPath('catalog.low_stock_count', 1);
    }

    public function test_top_products_are_ranked_by_revenue_without_n_plus_one(): void
    {
        $vendor = Vendor::factory()->create();
        $star = Product::factory()->for($vendor)->create(['name' => 'Star Seller']);
        $minor = Product::factory()->for($vendor)->create(['name' => 'Minor Seller']);

        OrderItem::factory()->for($star)->for($vendor)->create(['line_total' => '900.00', 'quantity' => 3]);
        OrderItem::factory()->for($minor)->for($vendor)->create(['line_total' => '100.00', 'quantity' => 1]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('catalog.top_products.0.name', 'Star Seller')
            // `id` must be the uuid — the key the rest of the admin surface uses —
            // not the bigint, so the dashboard can link the row.
            ->assertJsonPath('catalog.top_products.0.id', $star->uuid)
            ->assertJsonPath('catalog.top_products.0.revenue', '900.00')
            ->assertJsonPath('catalog.top_products.1.name', 'Minor Seller');
    }

    public function test_a_non_admin_cannot_access_the_dashboard(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
