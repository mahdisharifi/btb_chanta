<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\CategoryApiController;
use App\Http\Controllers\Api\V1\SearchApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
| Prefix: /api/v1
| Authentication: Laravel Sanctum
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Public endpoints (no auth required)
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');

    // Public product & category data
    Route::get('products', [ProductApiController::class, 'index'])->name('products.index');
    Route::get('products/{product:slug}', [ProductApiController::class, 'show'])->name('products.show');
    Route::get('categories', [CategoryApiController::class, 'index'])->name('categories.index');
    Route::get('categories/{category:slug}', [CategoryApiController::class, 'show'])->name('categories.show');
    Route::get('search', [SearchApiController::class, 'search'])->name('search');

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {

        // Auth management
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/user', [AuthController::class, 'user'])->name('auth.user');

        // Retailer-specific
        Route::middleware('role:retailer_premium|retailer_basic')->group(function () {
            Route::get('retail-prices', [ProductApiController::class, 'retailPrices'])->name('retail-prices.index');
            Route::post('retail-prices/{product}', [ProductApiController::class, 'setRetailPrice'])->name('retail-prices.store');
        });

        // Supplier-specific
        Route::middleware('role:supplier')->group(function () {
            Route::get('my-products', [ProductApiController::class, 'myProducts'])->name('my-products.index');
            Route::post('my-products', [ProductApiController::class, 'storeProduct'])->name('my-products.store');
            Route::put('my-products/{product}', [ProductApiController::class, 'updateProduct'])->name('my-products.update');
        });
    });
});
