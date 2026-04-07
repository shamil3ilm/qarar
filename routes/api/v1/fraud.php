<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Fraud\FraudAlertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fraud Detection Routes
|--------------------------------------------------------------------------
*/

// Alerts
Route::get('alerts',              [FraudAlertController::class, 'index'])->name('fraud.alerts.index');
Route::get('alerts/{id}',         [FraudAlertController::class, 'show'])->name('fraud.alerts.show');
Route::patch('alerts/{id}/status', [FraudAlertController::class, 'updateStatus'])->name('fraud.alerts.update-status');

// Rules
Route::get('rules',                    [FraudAlertController::class, 'rules'])->name('fraud.rules.index');
Route::post('rules',                   [FraudAlertController::class, 'storeRule'])->name('fraud.rules.store');
Route::patch('rules/{id}/toggle',      [FraudAlertController::class, 'toggleRule'])->name('fraud.rules.toggle');
Route::post('rules/seed-defaults',     [FraudAlertController::class, 'seedDefaults'])->name('fraud.rules.seed-defaults');
