<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerDetailResource;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * The customer base, newest first, each row carrying its lifetime order
     * count and realised spend. Both metrics are computed in this one query
     * (withCount / withSum) so the list stays O(1) queries regardless of size.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customers = User::query()
            ->where('role', UserRole::Customer)
            ->when(
                $request->filled('search'),
                function ($q) use ($request): void {
                    $term = trim((string) $request->string('search'));
                    $q->where(fn ($q) => $q
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
                },
            )
            ->withCount('orders')
            ->withSum(
                ['orders as total_spent' => fn ($q) => $q->whereIn('status', $this->realisedStates())],
                'total',
            )
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return CustomerResource::collection($customers);
    }

    /**
     * One customer's profile, recent orders, and saved addresses. Scoped to
     * customers — a vendor/admin id 404s rather than leaking a non-customer.
     */
    public function show(User $user): CustomerDetailResource
    {
        abort_unless($user->role === UserRole::Customer, 404);

        $user->loadCount('orders')
            ->loadSum(
                ['orders as total_spent' => fn ($q) => $q->whereIn('status', $this->realisedStates())],
                'total',
            )
            ->load([
                'orders' => fn ($q) => $q->latest('placed_at')->limit(5),
                'addresses' => fn ($q) => $q->orderByDesc('is_default'),
            ]);

        return CustomerDetailResource::make($user);
    }

    /**
     * Order states that represent money actually collected — what "total spent"
     * means. Pending isn't paid yet; cancelled was reversed. Both are excluded.
     *
     * @return array<int, string>
     */
    private function realisedStates(): array
    {
        return [
            OrderStatus::Paid->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];
    }
}
