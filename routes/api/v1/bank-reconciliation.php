<?php

use App\Http\Controllers\Api\V1\Accounting\BankReconciliationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bank Reconciliation Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 and wrapped in accounting middleware.
|
*/

Route::middleware(['auth:api'])->group(function () {

    // Bank Reconciliations
    Route::prefix('bank-reconciliations')->group(function () {
        Route::get('/', [BankReconciliationController::class, 'index'])
            ->middleware('check.permission:accounting.bank-reconciliation.view')
            ->name('bank-reconciliations.index');

        Route::post('/', [BankReconciliationController::class, 'store'])
            ->middleware('check.permission:accounting.bank-reconciliation.create')
            ->name('bank-reconciliations.store');

        Route::get('/{bankReconciliation}', [BankReconciliationController::class, 'show'])
            ->middleware('check.permission:accounting.bank-reconciliation.view')
            ->name('bank-reconciliations.show');

        Route::put('/{bankReconciliation}', [BankReconciliationController::class, 'update'])
            ->middleware('check.permission:accounting.bank-reconciliation.update')
            ->name('bank-reconciliations.update');

        Route::post('/{bankReconciliation}/auto-match', [BankReconciliationController::class, 'autoMatch'])
            ->middleware('check.permission:accounting.bank-reconciliation.update')
            ->name('bank-reconciliations.auto-match');

        Route::post('/{bankReconciliation}/manual-match', [BankReconciliationController::class, 'manualMatch'])
            ->middleware('check.permission:accounting.bank-reconciliation.update')
            ->name('bank-reconciliations.manual-match');

        Route::post('/{bankReconciliation}/unmatch/{itemId}', [BankReconciliationController::class, 'unmatch'])
            ->middleware('check.permission:accounting.bank-reconciliation.update')
            ->name('bank-reconciliations.unmatch');

        Route::post('/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])
            ->middleware('check.permission:accounting.bank-reconciliation.complete')
            ->name('bank-reconciliations.complete');
    });

    // Bank Statement Imports
    Route::post('/bank-statement-imports', [BankReconciliationController::class, 'importStatement'])
        ->middleware('check.permission:accounting.bank-reconciliation.import')
        ->name('bank-statement-imports.store');

    // EBS parsing — parse an uploaded MT940 or CAMT.053 file into BankTransactions
    Route::post('/bank-statement-imports/{importId}/parse', [BankReconciliationController::class, 'parseStatement'])
        ->middleware('check.permission:accounting.bank-reconciliation.import')
        ->name('bank-statement-imports.parse');
});
