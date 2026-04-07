<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\ActivityConfirmationController;
use App\Http\Controllers\Api\V1\Accounting\AssessmentCycleController;
use App\Http\Controllers\Api\V1\Accounting\CoRepostingController;
use App\Http\Controllers\Api\V1\Accounting\CostCenterController;
use App\Http\Controllers\Api\V1\Accounting\DistributionCycleController;
use App\Http\Controllers\Api\V1\Accounting\ProfitCenterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controlling Module Routes  (CO — Cost & Profit Centers)
|--------------------------------------------------------------------------
|
| All routes are mounted under  /api/v1/controlling
| and protected by the accounting module check + JWT middleware stack.
|
*/

Route::middleware(['auth:api'])->group(function (): void {

    // ================================================================
    // Cost Centers
    // ================================================================

    Route::prefix('cost-centers')->group(function (): void {

        // Standard hierarchy tree (SAP CO OKEON)
        Route::get('/hierarchy-tree', [CostCenterController::class, 'hierarchyTree'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.hierarchy-tree');

        // Aggregate report (no {costCenter} binding — must be before {costCenter})
        Route::get('/report', [CostCenterController::class, 'reportAll'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.report-all');

        // List
        Route::get('/', [CostCenterController::class, 'index'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.index');

        // Create
        Route::post('/', [CostCenterController::class, 'store'])
            ->middleware('check.permission:accounting.controlling.cost-center.create')
            ->name('controlling.cost-centers.store');

        // Show
        Route::get('/{costCenter}', [CostCenterController::class, 'show'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.show');

        // Update
        Route::put('/{costCenter}', [CostCenterController::class, 'update'])
            ->middleware('check.permission:accounting.controlling.cost-center.update')
            ->name('controlling.cost-centers.update');

        // Delete
        Route::delete('/{costCenter}', [CostCenterController::class, 'destroy'])
            ->middleware('check.permission:accounting.controlling.cost-center.delete')
            ->name('controlling.cost-centers.destroy');

        // Deactivate
        Route::post('/{costCenter}/deactivate', [CostCenterController::class, 'deactivate'])
            ->middleware('check.permission:accounting.controlling.cost-center.update')
            ->name('controlling.cost-centers.deactivate');

        // Assign employee
        Route::post('/{costCenter}/assign', [CostCenterController::class, 'assign'])
            ->middleware('check.permission:accounting.controlling.cost-center.assign')
            ->name('controlling.cost-centers.assign');

        // Single cost center report
        Route::get('/{costCenter}/report', [CostCenterController::class, 'report'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.report');

        // Plan vs actual report
        Route::get('/{costCenter}/plan-vs-actual', [CostCenterController::class, 'planVsActual'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.plan-vs-actual');

        // Set period plan (upsert)
        Route::post('/{costCenter}/plan', [CostCenterController::class, 'setPlan'])
            ->middleware('check.permission:accounting.controlling.cost-center.update')
            ->name('controlling.cost-centers.plan.set');

        // Get period plan matrix
        Route::get('/{costCenter}/plan', [CostCenterController::class, 'getPlan'])
            ->middleware('check.permission:accounting.controlling.cost-center.view')
            ->name('controlling.cost-centers.plan.get');
    });

    // ================================================================
    // Cost Allocations
    // ================================================================

    Route::prefix('allocations')->group(function (): void {

        // List
        Route::get('/', [CostCenterController::class, 'allocations'])
            ->middleware('check.permission:accounting.controlling.allocation.view')
            ->name('controlling.allocations.index');

        // Create
        Route::post('/', [CostCenterController::class, 'storeAllocation'])
            ->middleware('check.permission:accounting.controlling.allocation.create')
            ->name('controlling.allocations.store');

        // Show
        Route::get('/{allocation}', [CostCenterController::class, 'showAllocation'])
            ->middleware('check.permission:accounting.controlling.allocation.view')
            ->name('controlling.allocations.show');

        // Post
        Route::post('/{allocation}/post', [CostCenterController::class, 'postAllocation'])
            ->middleware('check.permission:accounting.controlling.allocation.post')
            ->name('controlling.allocations.post');
    });

    // ================================================================
    // Profit Centers
    // ================================================================

    Route::prefix('profit-centers')->group(function (): void {

        // Aggregate report (must be before {profitCenter} binding)
        Route::get('/report', [ProfitCenterController::class, 'reportAll'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.report-all');

        // List
        Route::get('/', [ProfitCenterController::class, 'index'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.index');

        // Create
        Route::post('/', [ProfitCenterController::class, 'store'])
            ->middleware('check.permission:accounting.controlling.profit-center.create')
            ->name('controlling.profit-centers.store');

        // Show
        Route::get('/{profitCenter}', [ProfitCenterController::class, 'show'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.show');

        // Update
        Route::put('/{profitCenter}', [ProfitCenterController::class, 'update'])
            ->middleware('check.permission:accounting.controlling.profit-center.update')
            ->name('controlling.profit-centers.update');

        // Delete
        Route::delete('/{profitCenter}', [ProfitCenterController::class, 'destroy'])
            ->middleware('check.permission:accounting.controlling.profit-center.delete')
            ->name('controlling.profit-centers.destroy');

        // Deactivate
        Route::post('/{profitCenter}/deactivate', [ProfitCenterController::class, 'deactivate'])
            ->middleware('check.permission:accounting.controlling.profit-center.update')
            ->name('controlling.profit-centers.deactivate');

        // Single profit center report
        Route::get('/{profitCenter}/report', [ProfitCenterController::class, 'report'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.report');

        // Set period plan
        Route::post('/{profitCenter}/plan', [ProfitCenterController::class, 'setPlan'])
            ->middleware('check.permission:accounting.controlling.profit-center.update')
            ->name('controlling.profit-centers.plan.set');

        // Get period plan
        Route::get('/{profitCenter}/plan', [ProfitCenterController::class, 'getPlan'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.plan.get');

        // Plan vs actual report
        Route::get('/{profitCenter}/plan-vs-actual', [ProfitCenterController::class, 'planVsActual'])
            ->middleware('check.permission:accounting.controlling.profit-center.view')
            ->name('controlling.profit-centers.plan-vs-actual');
    });

    // ================================================================
    // CO Manual Repostings (KB11N equivalent)
    // ================================================================

    Route::apiResource('co-repostings', CoRepostingController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->names('controlling.repostings')
        ->middleware('check.permission:accounting.controlling.reposting.view');

    Route::post('co-repostings/{coReposting}/reverse', [CoRepostingController::class, 'reverse'])
        ->middleware('check.permission:accounting.controlling.reposting.create')
        ->name('controlling.repostings.reverse');

    // ================================================================
    // CO-OM Assessment Cycles (secondary cost allocation + GL posting)
    // ================================================================

    Route::prefix('assessment-cycles')->name('controlling.assessment-cycles.')->group(function (): void {
        Route::get('/', [AssessmentCycleController::class, 'index'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('index');

        Route::post('/', [AssessmentCycleController::class, 'store'])
            ->middleware('check.permission:accounting.controlling.cycle.create')
            ->name('store');

        Route::get('/{assessmentCycle}', [AssessmentCycleController::class, 'show'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('show');

        Route::put('/{assessmentCycle}', [AssessmentCycleController::class, 'update'])
            ->middleware('check.permission:accounting.controlling.cycle.update')
            ->name('update');

        Route::delete('/{assessmentCycle}', [AssessmentCycleController::class, 'destroy'])
            ->middleware('check.permission:accounting.controlling.cycle.delete')
            ->name('destroy');

        Route::post('/{assessmentCycle}/execute', [AssessmentCycleController::class, 'execute'])
            ->middleware('check.permission:accounting.controlling.cycle.execute')
            ->name('execute');

        Route::post('/{assessmentCycle}/reverse', [AssessmentCycleController::class, 'reverse'])
            ->middleware('check.permission:accounting.controlling.cycle.execute')
            ->name('reverse');

        Route::get('/{assessmentCycle}/postings', [AssessmentCycleController::class, 'postings'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('postings');
    });

    // ================================================================
    // CO-OM Distribution Cycles (primary cost redistribution)
    // ================================================================

    Route::prefix('distribution-cycles')->name('controlling.distribution-cycles.')->group(function (): void {
        Route::get('/', [DistributionCycleController::class, 'index'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('index');

        Route::post('/', [DistributionCycleController::class, 'store'])
            ->middleware('check.permission:accounting.controlling.cycle.create')
            ->name('store');

        Route::get('/{distributionCycle}', [DistributionCycleController::class, 'show'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('show');

        Route::put('/{distributionCycle}', [DistributionCycleController::class, 'update'])
            ->middleware('check.permission:accounting.controlling.cycle.update')
            ->name('update');

        Route::delete('/{distributionCycle}', [DistributionCycleController::class, 'destroy'])
            ->middleware('check.permission:accounting.controlling.cycle.delete')
            ->name('destroy');

        Route::post('/{distributionCycle}/execute', [DistributionCycleController::class, 'execute'])
            ->middleware('check.permission:accounting.controlling.cycle.execute')
            ->name('execute');

        Route::post('/{distributionCycle}/reverse', [DistributionCycleController::class, 'reverse'])
            ->middleware('check.permission:accounting.controlling.cycle.execute')
            ->name('reverse');

        Route::get('/{distributionCycle}/postings', [DistributionCycleController::class, 'postings'])
            ->middleware('check.permission:accounting.controlling.cycle.view')
            ->name('postings');
    });

    // ================================================================
    // CO-ABC Activity Confirmations (actual activity quantity recording)
    // ================================================================

    Route::prefix('activity-confirmations')->name('controlling.activity-confirmations.')->group(function (): void {
        Route::get('/', [ActivityConfirmationController::class, 'index'])
            ->middleware('check.permission:accounting.controlling.activity-confirmation.view')
            ->name('index');

        Route::post('/', [ActivityConfirmationController::class, 'store'])
            ->middleware('check.permission:accounting.controlling.activity-confirmation.create')
            ->name('store');

        Route::get('/{activityConfirmation}', [ActivityConfirmationController::class, 'show'])
            ->middleware('check.permission:accounting.controlling.activity-confirmation.view')
            ->name('show');

        Route::post('/{activityConfirmation}/reverse', [ActivityConfirmationController::class, 'reverse'])
            ->middleware('check.permission:accounting.controlling.activity-confirmation.create')
            ->name('reverse');
    });
});
