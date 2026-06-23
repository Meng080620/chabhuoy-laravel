<?php

use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\CartController;
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
    });
});
