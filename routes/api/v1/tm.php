<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TM\TransportationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAP TM — Transportation Management Routes
|--------------------------------------------------------------------------
| Carrier Management, Freight Rate Engine, Tendering, Transportation Orders,
| Load Building / Consolidation (SAP TM equivalent).
*/

$ctrl = TransportationController::class;

// --- Carrier Management ---
Route::prefix('carriers')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listCarriers'])->name('tm.carriers.index');
    Route::post('/', [$ctrl, 'createCarrier'])->name('tm.carriers.store');
    Route::get('/{carrier}', [$ctrl, 'showCarrier'])->name('tm.carriers.show');
    Route::put('/{carrier}', [$ctrl, 'updateCarrier'])->name('tm.carriers.update');

    // Carrier services (service levels)
    Route::post('/{carrier}/services', [$ctrl, 'createCarrierService'])->name('tm.carriers.services.store');

    // Carrier performance KPIs
    Route::post('/{carrier}/performance', [$ctrl, 'recordCarrierPerformance'])->name('tm.carriers.performance.store');
    Route::get('/{carrier}/performance', [TransportationController::class, 'listCarriers'])
        ->name('tm.carriers.performance.index'); // placeholder — see showCarrier for performance data
});

// --- Freight Rate Tables ---
Route::prefix('rate-tables')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listRateTables'])->name('tm.rate-tables.index');
    Route::post('/', [$ctrl, 'createRateTable'])->name('tm.rate-tables.store');
    Route::post('/{rateTable}/lines', [$ctrl, 'addRateLine'])->name('tm.rate-tables.lines.store');
});

// Freight cost calculation (rate engine)
Route::post('/calculate-cost', [$ctrl, 'calculateCost'])->name('tm.calculate-cost');

// --- Freight Agreements ---
Route::prefix('agreements')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listAgreements'])->name('tm.agreements.index');
    Route::post('/', [$ctrl, 'createAgreement'])->name('tm.agreements.store');
    Route::post('/{agreement}/activate', [$ctrl, 'activateAgreement'])->name('tm.agreements.activate');
});

// --- Freight Tendering ---
Route::prefix('tenders')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listTenderRequests'])->name('tm.tenders.index');
    Route::post('/', [$ctrl, 'createTenderRequest'])->name('tm.tenders.store');
    Route::post('/{tender}/open', [$ctrl, 'openTenderRequest'])->name('tm.tenders.open');
    Route::post('/{tender}/bids', [$ctrl, 'submitBid'])->name('tm.tenders.bids.store');
    Route::post('/{tender}/evaluate', [$ctrl, 'evaluateBids'])->name('tm.tenders.evaluate');
    Route::post('/{tender}/award', [$ctrl, 'awardTender'])->name('tm.tenders.award');
});

// --- Transportation Orders ---
Route::prefix('orders')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listTransportationOrders'])->name('tm.orders.index');
    Route::post('/', [$ctrl, 'createTransportationOrder'])->name('tm.orders.store');
    Route::get('/{order}', [$ctrl, 'showTransportationOrder'])->name('tm.orders.show');
    Route::post('/{order}/status', [$ctrl, 'updateOrderStatus'])->name('tm.orders.status');
});

// --- Load Plans (Load Building / Consolidation) ---
Route::prefix('load-plans')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listLoadPlans'])->name('tm.load-plans.index');
    Route::post('/', [$ctrl, 'createLoadPlan'])->name('tm.load-plans.store');
    Route::get('/{loadPlan}', [$ctrl, 'showLoadPlan'])->name('tm.load-plans.show');
    Route::post('/{loadPlan}/add', [$ctrl, 'addToLoadPlan'])->name('tm.load-plans.add');
    Route::delete('/{loadPlan}/remove', [$ctrl, 'removeFromLoadPlan'])->name('tm.load-plans.remove');
    Route::post('/{loadPlan}/dispatch', [$ctrl, 'dispatchLoadPlan'])->name('tm.load-plans.dispatch');
});

// --- Reports ---
Route::get('/reports/utilization', [$ctrl, 'utilizationReport'])->name('tm.reports.utilization');
