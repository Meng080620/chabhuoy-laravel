<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->fromActiveVendor()
            // Eager-load what ProductResource renders. Two extra queries total,
            // not one-per-row — keeps the list endpoint O(1) in query count.
            ->with(['category', 'vendor'])
            ->when(
                isset($filters['category_id']),
                fn ($q) => $q->where('category_id', $filters['category_id']),
            )
            ->when(
                isset($filters['vendor_id']),
                fn ($q) => $q->where('vendor_id', $filters['vendor_id']),
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?Product
    {
        return Product::find($id);
    }

    public function findForUpdate(int $id): ?Product
    {
        return Product::whereKey($id)->lockForUpdate()->first();
    }

    public function forVendor(int $vendorId): Collection
    {
        return Product::with(['category', 'vendor'])
            ->where('vendor_id', $vendorId)
            ->latest('id')
            ->get();
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh();
    }

    public function decrementStock(Product $product, int $quantity): void
    {
        $product->decrement('stock', $quantity);
    }

    public function incrementStock(Product $product, int $quantity): void
    {
        $product->increment('stock', $quantity);
    }
}
