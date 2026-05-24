<?php

use App\Http\Controllers\Api\V1\Manufacturing\CertificateOfAnalysisController;
use App\Http\Controllers\Api\V1\Manufacturing\QualityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quality Management API Routes
|--------------------------------------------------------------------------
|
| All routes are nested under the `manufacturing` prefix and module
| middleware group defined in routes/api.php.
|
*/

Route::prefix('quality')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Quality Plans
    |--------------------------------------------------------------------------
    */
    Route::prefix('plans')->group(function () {
        Route::get('/', [QualityController::class, 'indexPlans'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::post('/', [QualityController::class, 'storePlan'])
            ->middleware('check.permission:manufacturing.quality.create');

        Route::get('/{id}', [QualityController::class, 'showPlan'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::put('/{id}', [QualityController::class, 'updatePlan'])
            ->middleware('check.permission:manufacturing.quality.edit');

        Route::delete('/{id}', [QualityController::class, 'destroyPlan'])
            ->middleware('check.permission:manufacturing.quality.delete');
    });

    /*
    |--------------------------------------------------------------------------
    | Inspection Lots
    |--------------------------------------------------------------------------
    */
    Route::prefix('inspection-lots')->group(function () {
        Route::get('/', [QualityController::class, 'indexLots'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::post('/', [QualityController::class, 'storeLot'])
            ->middleware('check.permission:manufacturing.quality.create');

        Route::get('/{id}', [QualityController::class, 'showLot'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::post('/{id}/results', [QualityController::class, 'recordResults'])
            ->middleware('check.permission:manufacturing.quality.inspect');

        Route::post('/{id}/complete', [QualityController::class, 'completeLot'])
            ->middleware('check.permission:manufacturing.quality.inspect');
    });

    /*
    |--------------------------------------------------------------------------
    | Quality Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [QualityController::class, 'indexNotifications'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::post('/', [QualityController::class, 'storeNotification'])
            ->middleware('check.permission:manufacturing.quality.create');

        Route::get('/{id}', [QualityController::class, 'showNotification'])
            ->middleware('check.permission:manufacturing.quality.view');

        Route::post('/{id}/assign', [QualityController::class, 'assignNotification'])
            ->middleware('check.permission:manufacturing.quality.edit');

        Route::post('/{id}/resolve', [QualityController::class, 'resolveNotification'])
            ->middleware('check.permission:manufacturing.quality.resolve');

        Route::post('/{id}/close', [QualityController::class, 'closeNotification'])
            ->middleware('check.permission:manufacturing.quality.resolve');

        Route::get('/{id}/defects', [QualityController::class, 'listDefects'])
            ->middleware('check.permission:manufacturing.quality.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */
    Route::get('/stats', [QualityController::class, 'stats'])
        ->middleware('check.permission:manufacturing.quality.view');

    /*
    |--------------------------------------------------------------------------
    | Certificates of Analysis (Gap 13)
    |--------------------------------------------------------------------------
    */
    Route::post('certificates-of-analysis/from-lot', [CertificateOfAnalysisController::class, 'generateFromLot'])
        ->middleware('check.permission:manufacturing.quality.create')
        ->name('manufacturing.coa.from-lot');

    Route::apiResource('certificates-of-analysis', CertificateOfAnalysisController::class)
        ->parameters(['certificates-of-analysis' => 'certificateOfAnalysis'])
        ->middleware([
            'index'   => 'check.permission:manufacturing.quality.view',
            'show'    => 'check.permission:manufacturing.quality.view',
            'store'   => 'check.permission:manufacturing.quality.create',
            'update'  => 'check.permission:manufacturing.quality.edit',
            'destroy' => 'check.permission:manufacturing.quality.delete',
        ])
        ->names('manufacturing.coa');

    Route::post('certificates-of-analysis/{certificateOfAnalysis}/approve', [CertificateOfAnalysisController::class, 'approve'])
        ->middleware('check.permission:manufacturing.quality.edit')
        ->name('manufacturing.coa.approve');

    Route::post('certificates-of-analysis/{certificateOfAnalysis}/issue', [CertificateOfAnalysisController::class, 'issue'])
        ->middleware('check.permission:manufacturing.quality.edit')
        ->name('manufacturing.coa.issue');

    Route::post('certificates-of-analysis/{certificateOfAnalysis}/revoke', [CertificateOfAnalysisController::class, 'revoke'])
        ->middleware('check.permission:manufacturing.quality.edit')
        ->name('manufacturing.coa.revoke');
});
