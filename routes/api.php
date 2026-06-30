<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\CategoryController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\ProductController;
use App\Http\Controllers\Api\Vendor\OrderController as VendorOrderController;
use App\Http\Controllers\Api\Vendor\ProductController as VendorProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated (any role)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // Cart
    Route::get('cart', [CartController::class, 'show']);
    Route::put('cart', [CartController::class, 'update']);
    Route::delete('cart/{productId}', [CartController::class, 'destroy']);

    // Shipping addresses (customer)
    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    Route::patch('addresses/{address}/default', [AddressController::class, 'setDefault']);

    // Orders (customer)
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);

    /*
    |----------------------------------------------------------------------
    | Vendor
    |----------------------------------------------------------------------
    */
    Route::middleware(['vendor', 'abilities:vendor:manage'])->prefix('vendor')->group(function () {
        Route::apiResource('products', VendorProductController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('orders', [VendorOrderController::class, 'index']);
        Route::patch('orders/{order}', [VendorOrderController::class, 'update']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin
    |----------------------------------------------------------------------
    */
    Route::middleware(['admin', 'abilities:admin:manage'])->prefix('admin')->group(function () {
        Route::get('reports/sales', [ReportController::class, 'sales']);
        Route::get('vendors', [AdminVendorController::class, 'index']);
        Route::patch('vendors/{vendor}', [AdminVendorController::class, 'updateStatus']);
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::patch('orders/{order}', [AdminOrderController::class, 'update']);
        Route::get('products', [AdminProductController::class, 'index']);
        Route::patch('products/{product}', [AdminProductController::class, 'update']);
        // Category model resolves by slug for storefront URLs; admin mutations
        // bind by the stable numeric id (a rename changes the slug).
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category:id}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category:id}', [AdminCategoryController::class, 'destroy']);
        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::get('customers/{user}', [AdminCustomerController::class, 'show']);
    });
});
