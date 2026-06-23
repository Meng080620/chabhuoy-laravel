<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function find(int $id): ?Product;

    /**
     * Fetch a product with a pessimistic write lock. Must be called inside a
     * transaction — used during checkout to prevent overselling.
     */
    public function findForUpdate(int $id): ?Product;

    /** @return Collection<int, Product> */
    public function forVendor(int $vendorId): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product;

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product;

    public function decrementStock(Product $product, int $quantity): void;
}
