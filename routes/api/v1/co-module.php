<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\ActivityTypeController;
use App\Http\Controllers\Api\V1\Accounting\CopaController;
use App\Http\Controllers\Api\V1\Accounting\CostElementController;
use App\Http\Controllers\Api\V1\Accounting\InternalOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CO Module Routes — Cost Elements, Activity Types, Internal Orders, CO-PA
|--------------------------------------------------------------------------
|
| Mounted inside the accounting module middleware group.
| All routes require the accounting module to be enabled.
|
*/

Route::apiResource('cost-elements', CostElementController::class)
    ->names('accounting.cost-elements');

Route::apiResource('activity-types', ActivityTypeController::class)
    ->names('accounting.activity-types');

Route::post('activity-types/{activityType}/rates', [ActivityTypeController::class, 'setRate'])
    ->name('accounting.activity-types.set-rate');

Route::apiResource('internal-orders', InternalOrderController::class)
    ->names('accounting.internal-orders');

Route::post('internal-orders/{internalOrder}/release', [InternalOrderController::class, 'release'])
    ->name('accounting.internal-orders.release');

Route::post('internal-orders/{internalOrder}/settle', [InternalOrderController::class, 'settle'])
    ->name('accounting.internal-orders.settle');

Route::post('internal-orders/{internalOrder}/technically-complete', [InternalOrderController::class, 'technicallyComplete'])
    ->name('accounting.internal-orders.technically-complete');

Route::post('internal-orders/{internalOrder}/close', [InternalOrderController::class, 'close'])
    ->name('accounting.internal-orders.close');

Route::get('internal-orders/{internalOrder}/budget-status', [InternalOrderController::class, 'budgetStatus'])
    ->name('accounting.internal-orders.budget-status');

Route::get('internal-orders/{internalOrder}/variance', [InternalOrderController::class, 'variance'])
    ->name('accounting.internal-orders.variance');

Route::prefix('copa')->group(function (): void {
    Route::get('profitability', [CopaController::class, 'profitability'])
        ->name('accounting.copa.profitability');

    Route::get('dimension/{dimension}', [CopaController::class, 'dimensionBreakdown'])
        ->name('accounting.copa.dimension-breakdown');

    // ----------------------------------------------------------------
    // CO-PA Plan Data & Variance — Gap 2
    // ----------------------------------------------------------------

    Route::get('plan-versions', [CopaController::class, 'planVersions'])
        ->name('accounting.copa.plan-versions.index');

    Route::post('plan-versions', [CopaController::class, 'storePlanVersion'])
        ->name('accounting.copa.plan-versions.store');

    Route::post('plan-versions/{version}/items', [CopaController::class, 'storePlanItems'])
        ->name('accounting.copa.plan-versions.items.store');

    Route::get('variance', [CopaController::class, 'varianceReport'])
        ->name('accounting.copa.variance');
});
