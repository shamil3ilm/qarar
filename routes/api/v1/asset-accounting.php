<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\AssetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Asset Accounting (FI-AA) Routes
|--------------------------------------------------------------------------
|
| All routes are nested inside the accounting module middleware group
| (auth:api, validate.jwt, check.organization, check.module:accounting).
|
*/

Route::middleware(['auth:api'])->group(function (): void {

    // =========================================================================
    // Asset Categories
    // =========================================================================

    Route::prefix('asset-categories')->group(function (): void {

        Route::get('/', [AssetController::class, 'categoriesIndex'])
            ->middleware('check.permission:accounting.asset_categories.view')
            ->name('accounting.asset_categories.index');

        Route::post('/', [AssetController::class, 'categoriesStore'])
            ->middleware('check.permission:accounting.asset_categories.create')
            ->name('accounting.asset_categories.store');

        Route::get('/{assetCategory}', [AssetController::class, 'categoriesShow'])
            ->middleware('check.permission:accounting.asset_categories.view')
            ->name('accounting.asset_categories.show');

        Route::put('/{assetCategory}', [AssetController::class, 'categoriesUpdate'])
            ->middleware('check.permission:accounting.asset_categories.update')
            ->name('accounting.asset_categories.update');

        Route::delete('/{assetCategory}', [AssetController::class, 'categoriesDestroy'])
            ->middleware('check.permission:accounting.asset_categories.delete')
            ->name('accounting.asset_categories.destroy');
    });

    // =========================================================================
    // Fixed Assets
    // =========================================================================

    Route::prefix('assets')->group(function (): void {

        Route::get('/', [AssetController::class, 'index'])
            ->middleware('check.permission:accounting.assets.view')
            ->name('accounting.assets.index');

        Route::post('/', [AssetController::class, 'store'])
            ->middleware('check.permission:accounting.assets.create')
            ->name('accounting.assets.store');

        Route::get('/{fixedAsset}', [AssetController::class, 'show'])
            ->middleware('check.permission:accounting.assets.view')
            ->name('accounting.assets.show');

        Route::put('/{fixedAsset}', [AssetController::class, 'update'])
            ->middleware('check.permission:accounting.assets.update')
            ->name('accounting.assets.update');

        Route::delete('/{fixedAsset}', [AssetController::class, 'destroy'])
            ->middleware('check.permission:accounting.assets.delete')
            ->name('accounting.assets.destroy');

        Route::get('/{fixedAsset}/schedule', [AssetController::class, 'schedule'])
            ->middleware('check.permission:accounting.assets.view')
            ->name('accounting.assets.schedule');

        Route::post('/{fixedAsset}/dispose', [AssetController::class, 'disposeAsset'])
            ->middleware('check.permission:accounting.assets.dispose')
            ->name('accounting.assets.dispose');

        Route::post('/{fixedAsset}/settle-auc', [AssetController::class, 'settleAuC'])
            ->middleware('check.permission:accounting.assets.dispose')
            ->name('accounting.assets.settle-auc');
    });

    // =========================================================================
    // Depreciation Runs
    // =========================================================================

    Route::prefix('depreciation-runs')->group(function (): void {

        Route::get('/', [AssetController::class, 'depreciation_runs_index'])
            ->middleware('check.permission:accounting.depreciation_runs.view')
            ->name('accounting.depreciation_runs.index');

        Route::post('/', [AssetController::class, 'runDepreciation'])
            ->middleware('check.permission:accounting.depreciation_runs.create')
            ->name('accounting.depreciation_runs.store');

        Route::get('/{depreciationRun}', [AssetController::class, 'depreciation_runs_show'])
            ->middleware('check.permission:accounting.depreciation_runs.view')
            ->name('accounting.depreciation_runs.show');

        Route::post('/{depreciationRun}/post', [AssetController::class, 'postRun'])
            ->middleware('check.permission:accounting.depreciation_runs.post')
            ->name('accounting.depreciation_runs.post');
    });
});
