<?php

use App\Http\Controllers\Api\V1\HR\PerformanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Performance Management Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/hr/performance (applied in api.php)
|
*/

Route::middleware(['auth:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Appraisal Cycles
    |--------------------------------------------------------------------------
    */
    Route::prefix('cycles')->group(function (): void {
        Route::get('/', [PerformanceController::class, 'indexCycles'])
            ->middleware('check.permission:hr.performance.cycles.view')
            ->name('hr.performance.cycles.index');

        Route::post('/', [PerformanceController::class, 'storeCycle'])
            ->middleware('check.permission:hr.performance.cycles.create')
            ->name('hr.performance.cycles.store');

        Route::get('/{id}', [PerformanceController::class, 'showCycle'])
            ->middleware('check.permission:hr.performance.cycles.view')
            ->name('hr.performance.cycles.show');

        Route::put('/{id}', [PerformanceController::class, 'updateCycle'])
            ->middleware('check.permission:hr.performance.cycles.edit')
            ->name('hr.performance.cycles.update');

        Route::post('/{id}/activate', [PerformanceController::class, 'activateCycle'])
            ->middleware('check.permission:hr.performance.cycles.manage')
            ->name('hr.performance.cycles.activate');

        Route::post('/{id}/complete', [PerformanceController::class, 'completeCycle'])
            ->middleware('check.permission:hr.performance.cycles.manage')
            ->name('hr.performance.cycles.complete');

        Route::get('/{id}/statistics', [PerformanceController::class, 'cycleStatistics'])
            ->middleware('check.permission:hr.performance.cycles.view')
            ->name('hr.performance.cycles.statistics');
    });

    /*
    |--------------------------------------------------------------------------
    | Appraisal Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('templates')->group(function (): void {
        Route::get('/', [PerformanceController::class, 'indexTemplates'])
            ->middleware('check.permission:hr.performance.templates.view')
            ->name('hr.performance.templates.index');

        Route::post('/', [PerformanceController::class, 'storeTemplate'])
            ->middleware('check.permission:hr.performance.templates.create')
            ->name('hr.performance.templates.store');

        Route::get('/{id}', [PerformanceController::class, 'showTemplate'])
            ->middleware('check.permission:hr.performance.templates.view')
            ->name('hr.performance.templates.show');

        Route::put('/{id}', [PerformanceController::class, 'updateTemplate'])
            ->middleware('check.permission:hr.performance.templates.edit')
            ->name('hr.performance.templates.update');

        Route::delete('/{id}', [PerformanceController::class, 'destroyTemplate'])
            ->middleware('check.permission:hr.performance.templates.delete')
            ->name('hr.performance.templates.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Performance Appraisals
    |--------------------------------------------------------------------------
    */
    Route::prefix('appraisals')->group(function (): void {
        Route::get('/', [PerformanceController::class, 'indexAppraisals'])
            ->middleware('check.permission:hr.performance.appraisals.view')
            ->name('hr.performance.appraisals.index');

        Route::get('/{id}', [PerformanceController::class, 'showAppraisal'])
            ->middleware('check.permission:hr.performance.appraisals.view')
            ->name('hr.performance.appraisals.show');

        Route::post('/{id}/self-review', [PerformanceController::class, 'submitSelfReview'])
            ->middleware('check.permission:hr.performance.appraisals.self-review')
            ->name('hr.performance.appraisals.self-review');

        Route::post('/{id}/manager-review', [PerformanceController::class, 'submitManagerReview'])
            ->middleware('check.permission:hr.performance.appraisals.manager-review')
            ->name('hr.performance.appraisals.manager-review');

        Route::post('/{id}/acknowledge', [PerformanceController::class, 'acknowledgeAppraisal'])
            ->middleware('check.permission:hr.performance.appraisals.acknowledge')
            ->name('hr.performance.appraisals.acknowledge');
    });

    /*
    |--------------------------------------------------------------------------
    | Performance Goals
    |--------------------------------------------------------------------------
    */
    Route::prefix('goals')->group(function (): void {
        Route::get('/', [PerformanceController::class, 'indexGoals'])
            ->middleware('check.permission:hr.performance.goals.view')
            ->name('hr.performance.goals.index');

        Route::post('/', [PerformanceController::class, 'storeGoal'])
            ->middleware('check.permission:hr.performance.goals.create')
            ->name('hr.performance.goals.store');

        Route::get('/{id}', [PerformanceController::class, 'showGoal'])
            ->middleware('check.permission:hr.performance.goals.view')
            ->name('hr.performance.goals.show');

        Route::put('/{id}', [PerformanceController::class, 'updateGoal'])
            ->middleware('check.permission:hr.performance.goals.edit')
            ->name('hr.performance.goals.update');

        Route::delete('/{id}', [PerformanceController::class, 'destroyGoal'])
            ->middleware('check.permission:hr.performance.goals.delete')
            ->name('hr.performance.goals.destroy');

        Route::post('/{id}/update-progress', [PerformanceController::class, 'updateGoalProgress'])
            ->middleware('check.permission:hr.performance.goals.update-progress')
            ->name('hr.performance.goals.update-progress');
    });
});
