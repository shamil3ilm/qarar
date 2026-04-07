<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\ModuleReadinessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Module Readiness / Pre-Activation Validation Routes
|--------------------------------------------------------------------------
|
| Routes that allow admins to validate whether their data is ready before
| activating a new ERP module for their organization.
|
*/

Route::prefix('modules/{module}')->group(function () {
    // List all registered checks for the module
    Route::get('/checks', [ModuleReadinessController::class, 'listChecks'])
        ->name('module-readiness.checks.index');

    // Run checks and persist result
    Route::post('/run-checks', [ModuleReadinessController::class, 'runChecks'])
        ->middleware('check.permission:core.settings.edit')
        ->name('module-readiness.run');

    // Most recent result
    Route::get('/readiness-result', [ModuleReadinessController::class, 'getLastResult'])
        ->middleware('check.permission:core.settings.view')
        ->name('module-readiness.result.last');

    // Paginated history of results
    Route::get('/readiness-results', [ModuleReadinessController::class, 'listResults'])
        ->middleware('check.permission:core.settings.view')
        ->name('module-readiness.results.index');
});
