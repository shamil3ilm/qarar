<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Analytics\DataWarehouseController;
use App\Http\Controllers\Api\V1\Analytics\UserAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('activity', [UserAnalyticsController::class, 'activityLogs'])
    ->name('analytics.activity.index');

Route::get('features', [UserAnalyticsController::class, 'featureUsage'])
    ->name('analytics.features.index');

Route::get('sessions', [UserAnalyticsController::class, 'sessions'])
    ->name('analytics.sessions.index');

Route::get('clusters', [UserAnalyticsController::class, 'clusters'])
    ->name('analytics.clusters.index');

Route::get('users/{id}/clusters', [UserAnalyticsController::class, 'userClusters'])
    ->name('analytics.users.clusters');

Route::get('users/{id}/dimensions', [UserAnalyticsController::class, 'dimensions'])
    ->name('analytics.users.dimensions');

// Data Warehouse (SAP BW equivalent)
Route::prefix('warehouse')->name('analytics.warehouse.')->group(function () {
    Route::post('/sync', [DataWarehouseController::class, 'sync'])->name('sync');
    Route::post('/load-facts', [DataWarehouseController::class, 'loadFacts'])->name('load-facts');
    Route::get('/sales-cube', [DataWarehouseController::class, 'salesCube'])->name('sales-cube');
    Route::get('/purchase-cube', [DataWarehouseController::class, 'purchaseCube'])->name('purchase-cube');
    Route::get('/inventory-cube', [DataWarehouseController::class, 'inventoryCube'])->name('inventory-cube');
    Route::get('/top-products', [DataWarehouseController::class, 'topProducts'])->name('top-products');
    Route::get('/top-customers', [DataWarehouseController::class, 'topCustomers'])->name('top-customers');
    Route::get('/sales-trend', [DataWarehouseController::class, 'salesTrend'])->name('sales-trend');
});
