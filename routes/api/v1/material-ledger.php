<?php

use App\Http\Controllers\Api\V1\Accounting\MaterialLedgerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Material Ledger (MM-ML) Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/material-ledger
| and protected by the accounting module middleware.
|
*/

Route::prefix('material-ledger')->name('mm.ml.')->group(function (): void {
    Route::get('/records', [MaterialLedgerController::class, 'index'])->name('records.index');
    Route::get('/records/{productId}', [MaterialLedgerController::class, 'show'])->name('records.show');
    Route::post('/period-close', [MaterialLedgerController::class, 'runPeriodClose'])->name('period-close');
    Route::get('/period-report', [MaterialLedgerController::class, 'periodReport'])->name('period-report');
    Route::get('/closing-entries', [MaterialLedgerController::class, 'closingEntries'])->name('closing-entries');
});
