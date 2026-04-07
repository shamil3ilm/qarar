<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Projects\ProjectBudgetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PS Budget Availability Control Routes
|--------------------------------------------------------------------------
|
| SAP PS-style budget availability control: versions, line items,
| supplements, availability checks, and budget status.
|
*/

// Per-project budget management
Route::prefix('projects/{projectId}/budget')->name('ps.budget.')->group(function (): void {

    Route::get('/versions', [ProjectBudgetController::class, 'listVersions'])
        ->name('versions.index')
        ->middleware('check.permission:projects.budget.view');

    Route::post('/versions', [ProjectBudgetController::class, 'createVersion'])
        ->name('versions.store')
        ->middleware('check.permission:projects.budget.create');

    Route::get('/versions/{versionId}', [ProjectBudgetController::class, 'showVersion'])
        ->name('versions.show')
        ->middleware('check.permission:projects.budget.view');

    Route::put('/versions/{versionId}', [ProjectBudgetController::class, 'updateVersion'])
        ->name('versions.update')
        ->middleware('check.permission:projects.budget.edit');

    Route::post('/versions/{versionId}/activate', [ProjectBudgetController::class, 'activateVersion'])
        ->name('versions.activate')
        ->middleware('check.permission:projects.budget.edit');

    Route::post('/versions/{versionId}/line-items', [ProjectBudgetController::class, 'setLineItems'])
        ->name('line-items.set')
        ->middleware('check.permission:projects.budget.edit');

    Route::post('/versions/{versionId}/supplements', [ProjectBudgetController::class, 'createSupplement'])
        ->name('supplements.store')
        ->middleware('check.permission:projects.budget.create');

    Route::post(
        '/versions/{versionId}/supplements/{supplementId}/approve',
        [ProjectBudgetController::class, 'approveSupplement']
    )
        ->name('supplements.approve')
        ->middleware('check.permission:projects.budget.approve');

    Route::post(
        '/versions/{versionId}/supplements/{supplementId}/reject',
        [ProjectBudgetController::class, 'rejectSupplement']
    )
        ->name('supplements.reject')
        ->middleware('check.permission:projects.budget.approve');

    Route::get('/status', [ProjectBudgetController::class, 'budgetStatus'])
        ->name('status')
        ->middleware('check.permission:projects.budget.view');
});

// Cross-project availability check (no project prefix needed)
Route::post('projects/budget/check-availability', [ProjectBudgetController::class, 'checkAvailability'])
    ->name('ps.budget.check-availability')
    ->middleware('check.permission:projects.budget.view');
