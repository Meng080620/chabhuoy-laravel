<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function find(int $id): ?Order;

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator;

    /**
     * Persist an order together with its line items in one call.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $attributes, array $items): Order;
}
