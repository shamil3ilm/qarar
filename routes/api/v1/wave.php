<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\WaveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wave Management & Picking Routes
| Prefix: inventory/warehouse-mgmt  (applied in routes/api.php)
|--------------------------------------------------------------------------
*/

// Putaway Rules
Route::middleware('check.permission:inventory.warehouse-mgmt.view')->group(function () {
    Route::get('/putaway-rules', [WaveController::class, 'putawayIndex']);
});

Route::middleware('check.permission:inventory.warehouse-mgmt.manage')->group(function () {
    Route::post('/putaway-rules', [WaveController::class, 'putawayStore']);
    Route::put('/putaway-rules/{id}', [WaveController::class, 'putawayUpdate']);
    Route::delete('/putaway-rules/{id}', [WaveController::class, 'putawayDestroy']);
    Route::post('/putaway-suggest', [WaveController::class, 'putawaySuggest']);
});

// Wave Plans
Route::middleware('check.permission:inventory.warehouse-mgmt.view')->group(function () {
    Route::get('/waves', [WaveController::class, 'waveIndex']);
    Route::get('/waves/{id}', [WaveController::class, 'waveShow']);
});

Route::middleware('check.permission:inventory.warehouse-mgmt.manage')->group(function () {
    Route::post('/waves', [WaveController::class, 'waveStore']);
    Route::post('/waves/{id}/release', [WaveController::class, 'waveRelease']);
    Route::post('/waves/{id}/complete', [WaveController::class, 'waveComplete']);
});

// Picking Lists
Route::middleware('check.permission:inventory.warehouse-mgmt.view')->group(function () {
    Route::get('/picking-lists', [WaveController::class, 'pickingListIndex']);
    Route::get('/picking-lists/{id}', [WaveController::class, 'pickingListShow']);
});

Route::middleware('check.permission:inventory.warehouse-mgmt.pick')->group(function () {
    Route::post('/picking-lists/{id}/assign', [WaveController::class, 'pickingListAssign']);
    Route::post('/picking-lists/{id}/start', [WaveController::class, 'pickingListStart']);
    Route::post('/picking-lists/{id}/complete', [WaveController::class, 'pickingListComplete']);
    Route::post('/picking-list-lines/{id}/pick', [WaveController::class, 'pickLine']);
});

// Stats
Route::middleware('check.permission:inventory.warehouse-mgmt.view')->group(function () {
    Route::get('/stats', [WaveController::class, 'stats']);
});
