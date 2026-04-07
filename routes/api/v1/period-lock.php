<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\PeriodLockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting Period Lock Routes
|--------------------------------------------------------------------------
|
| Prefix: /api/v1/accounting/period-lock (loaded inside the accounting module group)
|
*/

Route::middleware(['auth:api'])->group(function () {
    // Check whether a given date falls within a locked period
    Route::get('/check', [PeriodLockController::class, 'checkPeriod'])
        ->name('accounting.period-lock.check');

    // List active overrides
    Route::get('/', [PeriodLockController::class, 'index'])
        ->middleware('check.permission:accounting.period-lock.manage')
        ->name('accounting.period-lock.index');

    // Grant an override
    Route::post('/', [PeriodLockController::class, 'store'])
        ->middleware('check.permission:accounting.period-lock.manage')
        ->name('accounting.period-lock.store');

    // Revoke an override
    Route::post('/{id}/revoke', [PeriodLockController::class, 'revoke'])
        ->middleware('check.permission:accounting.period-lock.manage')
        ->name('accounting.period-lock.revoke');
});
