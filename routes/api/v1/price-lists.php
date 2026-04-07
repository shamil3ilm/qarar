<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\PriceListController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Price List Management Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/price-lists
| Module: sales  |  Permissions: sales.price-lists.view / .manage
|
*/

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('price-lists')->group(function () {

        // Utility: resolve price for a contact+product+qty (no resource ID required)
        Route::get('/resolve-price', [PriceListController::class, 'resolvePrice'])
            ->middleware('check.permission:sales.price-lists.view')
            ->name('sales.price-lists.resolve-price');

        // Collection
        Route::get('/', [PriceListController::class, 'index'])
            ->middleware('check.permission:sales.price-lists.view')
            ->name('sales.price-lists.index');

        Route::post('/', [PriceListController::class, 'store'])
            ->middleware('check.permission:sales.price-lists.manage')
            ->name('sales.price-lists.store');

        // Single resource
        Route::get('/{priceList}', [PriceListController::class, 'show'])
            ->middleware('check.permission:sales.price-lists.view')
            ->name('sales.price-lists.show');

        Route::put('/{priceList}', [PriceListController::class, 'update'])
            ->middleware('check.permission:sales.price-lists.manage')
            ->name('sales.price-lists.update');

        Route::delete('/{priceList}', [PriceListController::class, 'destroy'])
            ->middleware('check.permission:sales.price-lists.manage')
            ->name('sales.price-lists.destroy');

        // Nested actions
        Route::post('/{priceList}/assign-contact', [PriceListController::class, 'assignToContact'])
            ->middleware('check.permission:sales.price-lists.manage')
            ->name('sales.price-lists.assign-contact');

        Route::post('/{priceList}/import-items', [PriceListController::class, 'importItems'])
            ->middleware('check.permission:sales.price-lists.manage')
            ->name('sales.price-lists.import-items');
    });
});
