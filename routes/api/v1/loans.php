<?php

use App\Http\Controllers\Api\V1\Accounting\InterCompanyTransferController;
use App\Http\Controllers\Api\V1\Accounting\LoanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Loans & Inter-Company Transfer Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 and wrapped in accounting middleware.
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Loans
    |--------------------------------------------------------------------------
    */
    Route::prefix('loans')->group(function () {
        Route::get('/', [LoanController::class, 'index'])
            ->middleware('check.permission:accounting.loans.view')
            ->name('loans.index');

        Route::post('/', [LoanController::class, 'store'])
            ->middleware('check.permission:accounting.loans.create')
            ->name('loans.store');

        Route::get('/{loan}', [LoanController::class, 'show'])
            ->middleware('check.permission:accounting.loans.view')
            ->name('loans.show');

        Route::put('/{loan}', [LoanController::class, 'update'])
            ->middleware('check.permission:accounting.loans.update')
            ->name('loans.update');

        Route::post('/{loan}/review', [LoanController::class, 'review'])
            ->middleware('check.permission:accounting.loans.approve')
            ->name('loans.review');

        Route::post('/{loan}/payments', [LoanController::class, 'recordPayment'])
            ->middleware('check.permission:accounting.loans.payment')
            ->name('loans.record-payment');

        Route::get('/{loan}/outstanding-balance', [LoanController::class, 'outstandingBalance'])
            ->middleware('check.permission:accounting.loans.view')
            ->name('loans.outstanding-balance');

        Route::post('/{loan}/regenerate-schedule', [LoanController::class, 'regenerateSchedule'])
            ->middleware('check.permission:accounting.loans.update')
            ->name('loans.regenerate-schedule');

        Route::post('/{loan}/close', [LoanController::class, 'close'])
            ->middleware('check.permission:accounting.loans.close')
            ->name('loans.close');
    });

    /*
    |--------------------------------------------------------------------------
    | Inter-Company Transfers
    |--------------------------------------------------------------------------
    */
    Route::prefix('inter-company-transfers')->group(function () {
        Route::get('/', [InterCompanyTransferController::class, 'index'])
            ->middleware('check.permission:accounting.transfers.view')
            ->name('inter-company-transfers.index');

        Route::post('/', [InterCompanyTransferController::class, 'store'])
            ->middleware('check.permission:accounting.transfers.create')
            ->name('inter-company-transfers.store');

        Route::get('/{interCompanyTransfer}', [InterCompanyTransferController::class, 'show'])
            ->middleware('check.permission:accounting.transfers.view')
            ->name('inter-company-transfers.show');

        Route::post('/{interCompanyTransfer}/review', [InterCompanyTransferController::class, 'review'])
            ->middleware('check.permission:accounting.transfers.approve')
            ->name('inter-company-transfers.review');

        Route::post('/{interCompanyTransfer}/complete', [InterCompanyTransferController::class, 'complete'])
            ->middleware('check.permission:accounting.transfers.complete')
            ->name('inter-company-transfers.complete');
    });
});
