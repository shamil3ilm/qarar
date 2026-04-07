<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Aml\AmlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AML (Anti-Money Laundering) Routes
|--------------------------------------------------------------------------
*/

// Risk Scores
Route::get('risk-scores',              [AmlController::class, 'riskScores'])->name('aml.risk-scores.index');
Route::get('risk-scores/{contactId}',  [AmlController::class, 'contactRisk'])->name('aml.risk-scores.show');

// Flagged Transactions
Route::get('transactions/flagged',     [AmlController::class, 'transactionFlags'])->name('aml.transaction-flags.index');

// Suspicious Activity Reports
Route::get('sar',                      [AmlController::class, 'suspiciousActivities'])->name('aml.sar.index');
Route::post('sar',                     [AmlController::class, 'createSar'])->name('aml.sar.store');

// Contact Screening
Route::post('screen-contact/{contactId}', [AmlController::class, 'screenContact'])->name('aml.screen-contact');
