<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function find(int $id): ?Order
    {
        return Order::with('items')->find($id);
    }

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->orders()
            ->with('items')
            ->latest('placed_at')
            ->paginate($perPage);
    }

    public function create(array $attributes, array $items): Order
    {
        $order = Order::create($attributes);
        $order->items()->createMany($items);

        return $order->load('items');
    }
}
