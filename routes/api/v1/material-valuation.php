<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\MaterialValuationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Material Valuation Routes (MM — MAP, Standard Cost Variance, Revaluation)
|--------------------------------------------------------------------------
|
| All routes are nested under the `inventory` prefix and module middleware
| group defined in routes/api.php.
|
*/

Route::prefix('valuation')->group(function () {
    Route::get('inventory-value', [MaterialValuationController::class, 'inventoryValue'])
        ->middleware('check.permission:inventory.products.view')
        ->name('inventory.valuation.value');

    Route::post('revalue', [MaterialValuationController::class, 'revalue'])
        ->middleware('check.permission:inventory.products.edit')
        ->name('inventory.valuation.revalue');

    Route::get('variance-report', [MaterialValuationController::class, 'varianceReport'])
        ->middleware('check.permission:inventory.products.view')
        ->name('inventory.valuation.variance');
});
