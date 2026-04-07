<?php

use App\Http\Controllers\Api\V1\Accounting\MultiCurrencyController;
use Illuminate\Support\Facades\Route;

// Organization Currencies
Route::get('/currencies', [MultiCurrencyController::class, 'currencies']);
Route::post('/currencies', [MultiCurrencyController::class, 'addCurrency']);
Route::delete('/currencies/{currencyCode}', [MultiCurrencyController::class, 'removeCurrency']);

// Currency Revaluations
Route::prefix('revaluations')->group(function () {
    Route::get('/', [MultiCurrencyController::class, 'revaluations']);
    Route::post('/', [MultiCurrencyController::class, 'createRevaluation']);
    Route::post('/auto-run', [MultiCurrencyController::class, 'autoRunRevaluation']); // SAP F.05
    Route::get('/{currencyRevaluation}', [MultiCurrencyController::class, 'showRevaluation']);
    Route::post('/{currencyRevaluation}/post', [MultiCurrencyController::class, 'postRevaluation']);
    Route::post('/{currencyRevaluation}/reverse', [MultiCurrencyController::class, 'reverseRevaluation']);
});

// Forex Reports
Route::get('/forex-report', [MultiCurrencyController::class, 'forexReport']);
