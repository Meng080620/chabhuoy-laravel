<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin, $admin->role->abilities());
    }

    public function test_a_non_admin_cannot_list_customers(): void
    {
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson('/api/admin/customers')->assertForbidden();
    }

    public function test_index_lists_only_customers(): void
    {
        User::factory()->count(2)->create();   // customers (factory default)
        Vendor::factory()->create();           // a vendor's user — must be excluded
        $this->actAsAdmin();                   // an admin — must be excluded

        $this->getJson('/api/admin/customers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'orders_count', 'total_spent', 'created_at']],
                'links',
                'meta',
            ]);
    }

    public function test_total_spent_counts_only_realised_revenue(): void
    {
        $customer = User::factory()->create();
        // Realised: paid + shipped + delivered → counted.
        Order::factory()->for($customer)->status(OrderStatus::Paid)->create(['total' => 100]);
        Order::factory()->for($customer)->status(OrderStatus::Shipped)->create(['total' => 50]);
        Order::factory()->for($customer)->status(OrderStatus::Delivered)->create(['total' => 25]);
        // Not realised: pending (unpaid) + cancelled (reversed) → excluded from spend.
        Order::factory()->for($customer)->status(OrderStatus::Pending)->create(['total' => 999]);
        Order::factory()->for($customer)->status(OrderStatus::Cancelled)->create(['total' => 999]);

        $this->actAsAdmin();

        $this->getJson('/api/admin/customers')
            ->assertOk()
            ->assertJsonPath('data.0.orders_count', 5)        // every order placed
            ->assertJsonPath('data.0.total_spent', '175.00'); // only realised
    }

    public function test_index_can_search_by_name_or_email(): void
    {
        User::factory()->create(['name' => 'Sokha Pich', 'email' => 'sokha@example.com']);
        User::factory()->create(['name' => 'Dara Chan', 'email' => 'dara@example.com']);
        $this->actAsAdmin();

        $this->getJson('/api/admin/customers?search=sokha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'sokha@example.com');

        $this->getJson('/api/admin/customers?search=dara@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dara Chan');
    }

    public function test_index_does_not_n_plus_one_on_aggregates(): void
    {
        // Aggregates must be computed in the list query, not per-row. Query count
        // stays flat as customers grow — an N+1 would scale ~2x per customer.
        User::factory()->count(5)->create()->each(function (User $u): void {
            Order::factory()->for($u)->count(2)->create();
        });
        $this->actAsAdmin();

        DB::enableQueryLog();
        $this->getJson('/api/admin/customers')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $queryCount, "Expected a flat query count, ran {$queryCount} — likely an N+1 on the aggregates.");
    }

    public function test_show_returns_profile_recent_orders_and_addresses(): void
    {
        $customer = User::factory()->create();
        Order::factory()->for($customer)->status(OrderStatus::Delivered)->create(['total' => 80]);
        Address::factory()->create(['user_id' => $customer->id]);
        $this->actAsAdmin();

        $this->getJson("/api/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.total_spent', '80.00')
            ->assertJsonCount(1, 'data.recent_orders')
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'email', 'orders_count', 'total_spent', 'created_at',
                    'recent_orders' => [['id', 'status', 'total', 'placed_at']],
                    'addresses' => [['id', 'recipient_name', 'city', 'is_default']],
                ],
            ]);
    }

    public function test_show_404s_for_a_non_customer(): void
    {
        $vendor = Vendor::factory()->create(); // vendor's user is not a customer
        $this->actAsAdmin();

        $this->getJson("/api/admin/customers/{$vendor->user->id}")
            ->assertNotFound();
    }

    public function test_a_non_admin_cannot_view_a_customer(): void
    {
        $customer = User::factory()->create();
        $vendor = Vendor::factory()->create();
        Sanctum::actingAs($vendor->user, $vendor->user->role->abilities());

        $this->getJson("/api/admin/customers/{$customer->id}")->assertForbidden();
    }
}
