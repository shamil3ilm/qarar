<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Purchase\SupplierPerformanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase — Supplier Performance Routes
|--------------------------------------------------------------------------
|
| All routes are loaded inside the 'purchase' module group in routes/api.php,
| so check.module:purchase and the JWT middleware stack are already applied.
| The parent prefix is 'purchase/supplier-performance' (see routes/api.php).
|
*/

// Evaluation criteria
Route::get('/criteria', [SupplierPerformanceController::class, 'indexCriteria'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::post('/criteria', [SupplierPerformanceController::class, 'storeCriteria'])
    ->middleware('check.permission:purchase.suppliers.create');

Route::put('/criteria/{id}', [SupplierPerformanceController::class, 'updateCriteria'])
    ->middleware('check.permission:purchase.suppliers.edit');

Route::delete('/criteria/{id}', [SupplierPerformanceController::class, 'destroyCriteria'])
    ->middleware('check.permission:purchase.suppliers.delete');

// Scorecards
Route::get('/scorecards', [SupplierPerformanceController::class, 'indexScorecards'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::post('/scorecards', [SupplierPerformanceController::class, 'storeScorecard'])
    ->middleware('check.permission:purchase.suppliers.create');

Route::get('/scorecards/{id}', [SupplierPerformanceController::class, 'showScorecard'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::put('/scorecards/{id}', [SupplierPerformanceController::class, 'updateScorecard'])
    ->middleware('check.permission:purchase.suppliers.edit');

Route::post('/scorecards/{id}/finalize', [SupplierPerformanceController::class, 'finalizeScorecard'])
    ->middleware('check.permission:purchase.suppliers.edit');

// Delivery records
Route::get('/delivery-records', [SupplierPerformanceController::class, 'indexDeliveryRecords'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::post('/delivery-records', [SupplierPerformanceController::class, 'storeDeliveryRecord'])
    ->middleware('check.permission:purchase.suppliers.create');

// Incidents
Route::get('/incidents', [SupplierPerformanceController::class, 'indexIncidents'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::post('/incidents', [SupplierPerformanceController::class, 'storeIncident'])
    ->middleware('check.permission:purchase.suppliers.create');

Route::post('/incidents/{id}/resolve', [SupplierPerformanceController::class, 'resolveIncident'])
    ->middleware('check.permission:purchase.suppliers.edit');

// Analytics
Route::get('/suppliers/{supplierId}/stats', [SupplierPerformanceController::class, 'supplierStats'])
    ->middleware('check.permission:purchase.suppliers.view');

Route::get('/ranking', [SupplierPerformanceController::class, 'supplierRanking'])
    ->middleware('check.permission:purchase.suppliers.view');
