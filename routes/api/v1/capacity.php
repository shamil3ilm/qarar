<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Manufacturing\CapacityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing — Capacity Planning Routes
|--------------------------------------------------------------------------
|
| All routes in this file are loaded inside the 'manufacturing' module group
| defined in routes/api.php, so check.module:manufacturing is already applied.
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Work Centers
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-centers')->group(function () {
        Route::get('/', [CapacityController::class, 'indexWorkCenters'])
            ->middleware('check.permission:manufacturing.capacity.view');

        Route::post('/', [CapacityController::class, 'storeWorkCenter'])
            ->middleware('check.permission:manufacturing.capacity.create');

        Route::get('/{id}', [CapacityController::class, 'showWorkCenter'])
            ->middleware('check.permission:manufacturing.capacity.view');

        Route::put('/{id}', [CapacityController::class, 'updateWorkCenter'])
            ->middleware('check.permission:manufacturing.capacity.edit');

        Route::delete('/{id}', [CapacityController::class, 'destroyWorkCenter'])
            ->middleware('check.permission:manufacturing.capacity.delete');

        // Calendar exceptions
        Route::post('/{id}/exceptions', [CapacityController::class, 'storeException'])
            ->middleware('check.permission:manufacturing.capacity.edit');
    });

    /*
    |--------------------------------------------------------------------------
    | Capacity Reporting
    |--------------------------------------------------------------------------
    */
    Route::get('/load', [CapacityController::class, 'capacityLoad'])
        ->middleware('check.permission:manufacturing.capacity.view');

    Route::get('/bottlenecks', [CapacityController::class, 'bottlenecks'])
        ->middleware('check.permission:manufacturing.capacity.view');

    Route::get('/requirements', [CapacityController::class, 'requirements'])
        ->middleware('check.permission:manufacturing.capacity.view');

    /*
    |--------------------------------------------------------------------------
    | Work Order Capacity Actions
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-orders')->group(function () {
        Route::post('/{id}/plan', [CapacityController::class, 'planCapacity'])
            ->middleware('check.permission:manufacturing.capacity.create');

        Route::post('/{id}/reschedule', [CapacityController::class, 'reschedule'])
            ->middleware('check.permission:manufacturing.capacity.edit');

        Route::post('/{id}/release', [CapacityController::class, 'releaseCapacity'])
            ->middleware('check.permission:manufacturing.capacity.edit');
    });
});
