<?php

use App\Http\Controllers\Api\V1\Accounting\ConsolidationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FI Consolidation API Routes
|--------------------------------------------------------------------------
|
| All routes require the accounting module to be active (enforced by the
| parent group in routes/api.php) and a per-action permission.
|
*/

// Consolidation Groups
Route::prefix('groups')->group(function () {
    Route::get('/', [ConsolidationController::class, 'indexGroups'])
        ->middleware('check.permission:accounting.consolidation.view');

    Route::post('/', [ConsolidationController::class, 'storeGroup'])
        ->middleware('check.permission:accounting.consolidation.create');

    Route::get('/{id}', [ConsolidationController::class, 'showGroup'])
        ->middleware('check.permission:accounting.consolidation.view');

    Route::put('/{id}', [ConsolidationController::class, 'updateGroup'])
        ->middleware('check.permission:accounting.consolidation.edit');

    Route::delete('/{id}', [ConsolidationController::class, 'destroyGroup'])
        ->middleware('check.permission:accounting.consolidation.delete');

    // Entities nested under groups
    Route::post('/{id}/entities', [ConsolidationController::class, 'addEntity'])
        ->middleware('check.permission:accounting.consolidation.edit');
});

// Entity removal (direct entity ID)
Route::delete('/entities/{id}', [ConsolidationController::class, 'removeEntity'])
    ->middleware('check.permission:accounting.consolidation.edit');

// Consolidation Periods
Route::prefix('periods')->group(function () {
    Route::get('/', [ConsolidationController::class, 'indexPeriods'])
        ->middleware('check.permission:accounting.consolidation.view');

    Route::post('/', [ConsolidationController::class, 'storePeriod'])
        ->middleware('check.permission:accounting.consolidation.create');

    Route::get('/{id}', [ConsolidationController::class, 'showPeriod'])
        ->middleware('check.permission:accounting.consolidation.view');

    Route::post('/{id}/collect-balances', [ConsolidationController::class, 'collectBalances'])
        ->middleware('check.permission:accounting.consolidation.edit');

    Route::post('/{id}/complete', [ConsolidationController::class, 'completePeriod'])
        ->middleware('check.permission:accounting.consolidation.complete');

    Route::get('/{id}/report', [ConsolidationController::class, 'report'])
        ->middleware('check.permission:accounting.consolidation.view');

    // Elimination entries nested under periods
    Route::get('/{id}/eliminations', [ConsolidationController::class, 'indexEliminations'])
        ->middleware('check.permission:accounting.consolidation.view');

    Route::post('/{id}/eliminations', [ConsolidationController::class, 'storeElimination'])
        ->middleware('check.permission:accounting.consolidation.edit');

    // Auto-generated IC elimination entries
    Route::post('/{id}/generate-eliminations', [ConsolidationController::class, 'generateEliminations'])
        ->middleware('check.permission:accounting.consolidation.edit')
        ->name('consolidation.periods.generate-eliminations');

    Route::get('/{id}/eliminations-auto', [ConsolidationController::class, 'eliminationsAuto'])
        ->middleware('check.permission:accounting.consolidation.view')
        ->name('consolidation.periods.eliminations-auto');
});
