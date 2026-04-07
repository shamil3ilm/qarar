<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\ConsignmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Consignment Sales Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/consignment
| Module: sales  |  Permissions: sales.consignment.view / .manage
|
| Consignment order types:
|   fillup  — ship goods to customer consignment stock (no revenue)
|   issue   — customer reports consumption → invoice generated
|   pickup  — return unconsumed goods from customer
|   return  — customer returns previously billed goods (CN flow)
|
*/

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('consignment')->group(function () {

        // Utility: stock levels (no resource ID required — placed before /{consignment})
        Route::get('/stock', [ConsignmentController::class, 'stockLevel'])
            ->middleware('check.permission:sales.consignment.view')
            ->name('sales.consignment.stock');

        Route::get('/statement/{contactId}', [ConsignmentController::class, 'statement'])
            ->middleware('check.permission:sales.consignment.view')
            ->name('sales.consignment.statement');

        // Collection
        Route::get('/', [ConsignmentController::class, 'index'])
            ->middleware('check.permission:sales.consignment.view')
            ->name('sales.consignment.index');

        Route::post('/', [ConsignmentController::class, 'store'])
            ->middleware('check.permission:sales.consignment.manage')
            ->name('sales.consignment.store');

        // Single resource
        Route::get('/{consignment}', [ConsignmentController::class, 'show'])
            ->middleware('check.permission:sales.consignment.view')
            ->name('sales.consignment.show');

        // State transitions
        Route::post('/{consignment}/confirm', [ConsignmentController::class, 'confirm'])
            ->middleware('check.permission:sales.consignment.manage')
            ->name('sales.consignment.confirm');

        Route::post('/{consignment}/ship', [ConsignmentController::class, 'ship'])
            ->middleware('check.permission:sales.consignment.manage')
            ->name('sales.consignment.ship');

        Route::post('/{consignment}/complete', [ConsignmentController::class, 'complete'])
            ->middleware('check.permission:sales.consignment.manage')
            ->name('sales.consignment.complete');

        Route::post('/{consignment}/cancel', [ConsignmentController::class, 'cancel'])
            ->middleware('check.permission:sales.consignment.manage')
            ->name('sales.consignment.cancel');
    });
});
