<?php

use App\Http\Controllers\Api\V1\Manufacturing\ProductCostingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product Costing (SAP CO-PC) Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Costing Versions
    |--------------------------------------------------------------------------
    */
    Route::prefix('costing-versions')->group(function () {
        Route::get('/', [ProductCostingController::class, 'indexVersions'])
            ->middleware('check.permission:manufacturing.costing.view');

        Route::post('/', [ProductCostingController::class, 'storeVersion'])
            ->middleware('check.permission:manufacturing.costing.create');

        Route::get('/{version}', [ProductCostingController::class, 'showVersion'])
            ->middleware('check.permission:manufacturing.costing.view');

        // Costing run for a version
        Route::post('/{version}/run', [ProductCostingController::class, 'runCosting'])
            ->middleware('check.permission:manufacturing.costing.run');
    });

    /*
    |--------------------------------------------------------------------------
    | Costing Runs
    |--------------------------------------------------------------------------
    */
    Route::prefix('costing-runs')->group(function () {
        Route::get('/{run}', [ProductCostingController::class, 'showRunStatus'])
            ->middleware('check.permission:manufacturing.costing.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Standard Costs
    |--------------------------------------------------------------------------
    */
    Route::prefix('standard-costs')->group(function () {
        Route::get('/versions/{version}', [ProductCostingController::class, 'indexStandardCosts'])
            ->middleware('check.permission:manufacturing.costing.view');

        Route::get('/versions/{version}/products/{productId}', [ProductCostingController::class, 'showProductCost'])
            ->middleware('check.permission:manufacturing.costing.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Cost Variances
    |--------------------------------------------------------------------------
    */
    Route::prefix('cost-variances')->group(function () {
        Route::get('/', [ProductCostingController::class, 'indexVariances'])
            ->middleware('check.permission:manufacturing.costing.view');

        Route::post('/work-orders/{workOrder}/calculate', [ProductCostingController::class, 'calculateVariance'])
            ->middleware('check.permission:manufacturing.costing.run');
    });

    /*
    |--------------------------------------------------------------------------
    | WIP Valuations
    |--------------------------------------------------------------------------
    */
    Route::prefix('wip-valuations')->group(function () {
        Route::get('/', [ProductCostingController::class, 'indexWipValuations'])
            ->middleware('check.permission:manufacturing.costing.view');

        Route::post('/run', [ProductCostingController::class, 'valuateWip'])
            ->middleware('check.permission:manufacturing.costing.run');
    });
});
