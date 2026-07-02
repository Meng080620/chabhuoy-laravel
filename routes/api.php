<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\DeliveryCashSettlementController as AdminDeliveryCashSettlementController;
use App\Http\Controllers\Api\Admin\DeliveryEarningController as AdminDeliveryEarningController;
use App\Http\Controllers\Api\Admin\DeliveryManController as AdminDeliveryManController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\BrandStoreController as AdminBrandStoreController;
use App\Http\Controllers\Api\Customer\AddressController;
use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\BannerController;
use App\Http\Controllers\Api\Customer\BrandStoreController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\CategoryController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\ProductController;
use App\Http\Controllers\Api\DeliveryMan\CashSettlementController as DeliveryManCashSettlementController;
use App\Http\Controllers\Api\DeliveryMan\EarningsController as DeliveryManEarningsController;
use App\Http\Controllers\Api\DeliveryMan\OrderController as DeliveryManOrderController;
use App\Http\Controllers\Api\DeliveryMan\PresenceController as DeliveryManPresenceController;
use App\Http\Controllers\Api\Vendor\EarningsController as VendorEarningsController;
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

Route::get('banners', [BannerController::class, 'index']);
Route::get('brand-stores', [BrandStoreController::class, 'index']);

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
        Route::get('earnings', [VendorEarningsController::class, 'show']);
    });

    /*
    |----------------------------------------------------------------------
    | Delivery Man (rider)
    |----------------------------------------------------------------------
    */
    Route::middleware(['delivery-man', 'abilities:delivery:manage'])->prefix('delivery-man')->group(function () {
        Route::get('latest-orders', [DeliveryManOrderController::class, 'latestOrders']);
        Route::get('current-orders', [DeliveryManOrderController::class, 'currentOrders']);
        Route::get('all-orders', [DeliveryManOrderController::class, 'allOrders']);
        Route::patch('accept-order/{deliveryAssignment}', [DeliveryManOrderController::class, 'accept']);
        Route::patch('update-order-status/{deliveryAssignment}', [DeliveryManOrderController::class, 'updateStatus']);
        Route::patch('update-active-status', [DeliveryManPresenceController::class, 'updateActiveStatus']);
        Route::patch('record-location-data', [DeliveryManPresenceController::class, 'recordLocation']);
        Route::patch('update-fcm-token', [DeliveryManPresenceController::class, 'updateFcmToken']);
        Route::post('make-collected-cash-payment', [DeliveryManCashSettlementController::class, 'store']);
        Route::get('earnings', [DeliveryManEarningsController::class, 'show']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin
    |----------------------------------------------------------------------
    */
    Route::middleware(['admin', 'abilities:admin:manage'])->prefix('admin')->group(function () {
        Route::get('dashboard', [ReportController::class, 'dashboard']);
        Route::get('reports/sales', [ReportController::class, 'sales']);
        Route::get('vendors', [AdminVendorController::class, 'index']);
        Route::patch('vendors/{vendor}', [AdminVendorController::class, 'updateStatus']);
        Route::patch('vendors/{vendor}/commission', [AdminVendorController::class, 'updateCommission']);
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::patch('orders/{order}', [AdminOrderController::class, 'update']);
        Route::get('products', [AdminProductController::class, 'index']);
        Route::patch('products/{product}', [AdminProductController::class, 'update']);
        // Product image upload/remove; {product} binds by uuid (HasUuid route key).
        Route::post('products/{product}/image', [AdminProductController::class, 'uploadImage']);
        Route::delete('products/{product}/image', [AdminProductController::class, 'removeImage']);
        // Category model resolves by slug for storefront URLs; admin mutations
        // bind by the stable numeric id (a rename changes the slug).
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{category:id}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category:id}', [AdminCategoryController::class, 'destroy']);
        Route::get('customers', [AdminCustomerController::class, 'index']);
        Route::get('customers/{user}', [AdminCustomerController::class, 'show']);
        // Storefront CMS banners (hero/promo/eco/seasonal). Bind by numeric id;
        // banners are non-sensitive content like categories.
        Route::get('banners', [AdminBannerController::class, 'index']);
        Route::post('banners', [AdminBannerController::class, 'store']);
        Route::put('banners/{banner}', [AdminBannerController::class, 'update']);
        Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy']);
        // Storefront CMS brand-store tiles (slice 2). Bind by numeric id.
        Route::get('brand-stores', [AdminBrandStoreController::class, 'index']);
        Route::post('brand-stores', [AdminBrandStoreController::class, 'store']);
        Route::put('brand-stores/{brandStore}', [AdminBrandStoreController::class, 'update']);
        Route::delete('brand-stores/{brandStore}', [AdminBrandStoreController::class, 'destroy']);
        // Vendor disbursement ledger; {vendor} binds by uuid (HasUuid route key).
        Route::get('payouts', [AdminPayoutController::class, 'index']);
        Route::post('payouts/{vendor}', [AdminPayoutController::class, 'store']);
        // Delivery-man (rider) domain — {deliveryMan} binds by uuid.
        Route::get('delivery-men', [AdminDeliveryManController::class, 'index']);
        Route::patch('delivery-men/{deliveryMan}', [AdminDeliveryManController::class, 'updateStatus']);
        Route::get('delivery-earnings', [AdminDeliveryEarningController::class, 'index']);
        Route::post('delivery-earnings/{deliveryMan}', [AdminDeliveryEarningController::class, 'store']);
        Route::get('delivery-cash-settlements', [AdminDeliveryCashSettlementController::class, 'index']);
    });
});
