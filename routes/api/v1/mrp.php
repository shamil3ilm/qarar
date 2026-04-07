<?php

use App\Http\Controllers\Api\V1\Manufacturing\MrpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MRP (Material Requirements Planning) API Routes
|--------------------------------------------------------------------------
|
| All routes require the manufacturing module to be active (enforced by the
| parent group in routes/api.php) and a per-action permission.
|
*/

Route::prefix('mrp')->group(function () {
    // MRP Runs
    Route::get('/', [MrpController::class, 'index'])
        ->middleware('check.permission:manufacturing.mrp.view');

    Route::post('/run', [MrpController::class, 'run'])
        ->middleware('check.permission:manufacturing.mrp.run');

    Route::get('/{id}', [MrpController::class, 'show'])
        ->middleware('check.permission:manufacturing.mrp.view');

    Route::get('/{id}/planned-orders', [MrpController::class, 'plannedOrders'])
        ->middleware('check.permission:manufacturing.mrp.view');

    // Planned Order Actions
    Route::post('/planned-orders/{id}/firm', [MrpController::class, 'firmOrder'])
        ->middleware('check.permission:manufacturing.mrp.edit');

    Route::post('/planned-orders/{id}/convert', [MrpController::class, 'convertOrder'])
        ->middleware('check.permission:manufacturing.mrp.edit');

    // Demand Forecasts
    Route::get('/forecasts', [MrpController::class, 'forecasts'])
        ->middleware('check.permission:manufacturing.mrp.view');

    Route::post('/forecasts', [MrpController::class, 'storeForecast'])
        ->middleware('check.permission:manufacturing.mrp.edit');

    Route::put('/forecasts/{id}', [MrpController::class, 'updateForecast'])
        ->middleware('check.permission:manufacturing.mrp.edit');

    Route::delete('/forecasts/{id}', [MrpController::class, 'destroyForecast'])
        ->middleware('check.permission:manufacturing.mrp.delete');

    // Exceptions and Accuracy
    Route::get('/exceptions', [MrpController::class, 'exceptions'])
        ->middleware('check.permission:manufacturing.mrp.view');

    Route::get('/forecast-accuracy', [MrpController::class, 'forecastAccuracy'])
        ->middleware('check.permission:manufacturing.mrp.view');
});
