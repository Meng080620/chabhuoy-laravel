<?php

namespace App\Providers;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentVendorRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations so the rest
 * of the app depends on contracts, never on Eloquent directly. Swap an entry
 * here (e.g. to a cached decorator) without touching any consumer.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
        VendorRepositoryInterface::class => EloquentVendorRepository::class,
    ];

    public function register(): void
    {
        // $bindings above is resolved automatically by the container.
    }
}
