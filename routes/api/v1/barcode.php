<?php

use App\Http\Controllers\Api\V1\Inventory\BarcodeController;
use App\Http\Controllers\Api\V1\Inventory\PriceCheckController;
use App\Http\Controllers\Api\V1\Inventory\ShelfLabelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Barcode & Price Check API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/inventory
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Top-level convenience routes
    |--------------------------------------------------------------------------
    */
    Route::post('/lookup', [BarcodeController::class, 'lookupByValue'])->name('inventory.barcode.lookup');
    Route::post('/price-check', [PriceCheckController::class, 'quickCheck'])->name('inventory.barcode.price-check');

    /*
    |--------------------------------------------------------------------------
    | Barcodes
    |--------------------------------------------------------------------------
    */
    Route::prefix('barcodes')->group(function () {
        Route::get('/', [BarcodeController::class, 'index'])->name('inventory.barcodes.index');
        Route::post('/', [BarcodeController::class, 'store'])->name('inventory.barcodes.store');
        Route::post('/generate', [BarcodeController::class, 'generate'])->name('inventory.barcodes.generate');
        Route::post('/lookup', [BarcodeController::class, 'lookup'])->name('inventory.barcodes.lookup');
        Route::post('/bulk-generate', [BarcodeController::class, 'bulkGenerate'])->name('inventory.barcodes.bulk-generate');
        Route::post('/print-labels', [BarcodeController::class, 'printLabels'])->name('inventory.barcodes.print-labels');
        Route::get('/{barcode}', [BarcodeController::class, 'show'])->name('inventory.barcodes.show');
        Route::put('/{barcode}', [BarcodeController::class, 'update'])->name('inventory.barcodes.update');
        Route::delete('/{barcode}', [BarcodeController::class, 'destroy'])->name('inventory.barcodes.destroy');
    });

    // Product-scoped barcode routes
    Route::get('products/{product}/barcodes', [BarcodeController::class, 'listForProduct']);
    Route::post('products/{product}/barcodes', [BarcodeController::class, 'storeForProduct']);

    /*
    |--------------------------------------------------------------------------
    | Price Check
    |--------------------------------------------------------------------------
    */
    Route::prefix('price-check')->group(function () {
        Route::post('/check', [PriceCheckController::class, 'check'])->name('inventory.price-check.check');
        Route::get('/analytics', [PriceCheckController::class, 'analytics'])->name('inventory.price-check.analytics');
        Route::get('/logs', [PriceCheckController::class, 'logs'])->name('inventory.price-check.logs');

        // Stations
        Route::prefix('stations')->group(function () {
            Route::get('/', [PriceCheckController::class, 'stationIndex'])->name('inventory.price-check.stations.index');
            Route::post('/', [PriceCheckController::class, 'stationStore'])->name('inventory.price-check.stations.store');
            Route::get('/{station}', [PriceCheckController::class, 'stationShow'])->name('inventory.price-check.stations.show');
            Route::put('/{station}', [PriceCheckController::class, 'stationUpdate'])->name('inventory.price-check.stations.update');
            Route::delete('/{station}', [PriceCheckController::class, 'stationDestroy'])->name('inventory.price-check.stations.destroy');
            Route::get('/{station}/stats', [PriceCheckController::class, 'stationStats'])->name('inventory.price-check.stations.stats');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Shelf Labels
    |--------------------------------------------------------------------------
    */
    Route::prefix('shelf-labels')->group(function () {
        Route::get('/', [ShelfLabelController::class, 'index'])->name('inventory.shelf-labels.index');
        Route::post('/', [ShelfLabelController::class, 'store'])->name('inventory.shelf-labels.store');
        Route::post('/generate', [ShelfLabelController::class, 'generate'])->name('inventory.shelf-labels.generate');
        Route::post('/bulk-create', [ShelfLabelController::class, 'bulkCreate'])->name('inventory.shelf-labels.bulk-create');
        Route::post('/reprint', [ShelfLabelController::class, 'reprint'])->name('inventory.shelf-labels.reprint');
        Route::get('/{shelfLabel}', [ShelfLabelController::class, 'show'])->name('inventory.shelf-labels.show');
        Route::put('/{shelfLabel}', [ShelfLabelController::class, 'update'])->name('inventory.shelf-labels.update');
        Route::delete('/{shelfLabel}', [ShelfLabelController::class, 'destroy'])->name('inventory.shelf-labels.destroy');
    });
});
