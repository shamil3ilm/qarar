<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\RealEstate\RealEstateController;
use App\Http\Controllers\Api\V1\RealEstate\VacancyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAP RE-FX — Real Estate Flexible Framework Routes
|--------------------------------------------------------------------------
| Portfolio hierarchy, lease contracts, periodic posting, service charge
| settlement, security deposits, vacancy management.
*/

$ctrl = RealEstateController::class;

// --- Portfolio Management ---
Route::prefix('portfolios')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listPortfolios'])->name('re.portfolios.index');
    Route::post('/', [$ctrl, 'createPortfolio'])->name('re.portfolios.store');
    Route::get('/overview', [$ctrl, 'portfolioOverview'])->name('re.portfolios.overview');
});

// --- Properties ---
Route::prefix('properties')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listProperties'])->name('re.properties.index');
    Route::post('/', [$ctrl, 'createProperty'])->name('re.properties.store');
    Route::get('/{property}', [$ctrl, 'showProperty'])->name('re.properties.show');

    // Buildings within a property
    Route::post('/{property}/buildings', [$ctrl, 'createBuilding'])->name('re.properties.buildings.store');
});

// --- Buildings ---
Route::prefix('buildings')->group(function () use ($ctrl) {
    // Floors within a building
    Route::post('/{building}/floors', [$ctrl, 'createFloor'])->name('re.buildings.floors.store');

    // Rental units within a building
    Route::post('/{building}/units', [$ctrl, 'createRentalUnit'])->name('re.buildings.units.store');
});

// --- Rental Units ---
Route::prefix('units')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listRentalUnits'])->name('re.units.index');
    Route::get('/{unit}', [$ctrl, 'showRentalUnit'])->name('re.units.show');
    // Vacancy management
    Route::post('/{id}/vacate', [VacancyController::class, 'vacate'])->name('re.units.vacate');
    Route::post('/{id}/occupy', [VacancyController::class, 'occupy'])->name('re.units.occupy');
    Route::get('/{id}/vacancy-history', [VacancyController::class, 'vacancyHistory'])->name('re.units.vacancy-history');
});

Route::prefix('buildings')->group(function () {
    Route::get('/{buildingId}/vacant-units', [VacancyController::class, 'vacantUnits'])->name('re.buildings.vacant-units');
    Route::get('/{buildingId}/occupancy-trend', [VacancyController::class, 'occupancyTrend'])->name('re.buildings.occupancy-trend');
    Route::post('/{buildingId}/snapshot', [VacancyController::class, 'snapshot'])->name('re.buildings.snapshot');
});

// --- Lease Contracts ---
Route::prefix('contracts')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listContracts'])->name('re.contracts.index');
    Route::post('/', [$ctrl, 'createContract'])->name('re.contracts.store');
    Route::get('/{contract}', [$ctrl, 'showContract'])->name('re.contracts.show');
    Route::post('/{contract}/activate', [$ctrl, 'activateContract'])->name('re.contracts.activate');
    Route::post('/{contract}/terminate', [$ctrl, 'terminateContract'])->name('re.contracts.terminate');

    // Security deposits
    Route::post('/{contract}/deposits', [$ctrl, 'createDeposit'])->name('re.contracts.deposits.store');

    // IFRS 16 — Right-of-Use asset & lease liability amortisation
    Route::post('/{contract}/ifrs16/generate', [$ctrl, 'generateIfrs16'])->name('re.contracts.ifrs16.generate');
    Route::get('/{contract}/ifrs16/schedule', [$ctrl, 'ifrs16Schedule'])->name('re.contracts.ifrs16.schedule');
});

// --- Contract Options ---
Route::prefix('options')->group(function () use ($ctrl) {
    Route::post('/{option}/exercise', [$ctrl, 'exerciseOption'])->name('re.options.exercise');
});

// --- Rent Conditions / Escalation ---
Route::prefix('conditions')->group(function () use ($ctrl) {
    Route::post('/{condition}/escalate', [$ctrl, 'applyEscalation'])->name('re.conditions.escalate');
});

// --- Security Deposits ---
Route::prefix('deposits')->group(function () use ($ctrl) {
    Route::post('/{deposit}/collect', [$ctrl, 'recordDepositCollection'])->name('re.deposits.collect');
    Route::post('/{deposit}/accrue-interest', [$ctrl, 'accrueDepositInterest'])->name('re.deposits.accrue');
    Route::post('/{deposit}/refund', [$ctrl, 'refundDeposit'])->name('re.deposits.refund');
});

// --- Periodic Posting ---
Route::prefix('posting-runs')->group(function () use ($ctrl) {
    Route::post('/simulate', [$ctrl, 'simulatePostingRun'])->name('re.posting-runs.simulate');
    Route::post('/execute', [$ctrl, 'executePostingRun'])->name('re.posting-runs.execute');
});

// --- Service Charge Settlement ---
Route::prefix('settlements')->group(function () use ($ctrl) {
    Route::post('/', [$ctrl, 'createServiceChargeSettlement'])->name('re.settlements.store');
    Route::post('/{settlement}/calculate', [$ctrl, 'calculateSettlement'])->name('re.settlements.calculate');
});

// --- Reports ---
Route::prefix('reports')->group(function () use ($ctrl) {
    Route::get('/vacancy', [$ctrl, 'vacancyReport'])->name('re.reports.vacancy');
    Route::get('/occupancy-trend', [VacancyController::class, 'occupancyTrend'])->name('re.reports.occupancy-trend');
    Route::get('/expiring-contracts', [$ctrl, 'expiringContracts'])->name('re.reports.expiring');
    Route::get('/due-escalations', [$ctrl, 'dueEscalations'])->name('re.reports.escalations-due');
    Route::get('/upcoming-escalations', [$ctrl, 'upcomingEscalations'])->name('re.reports.escalations-upcoming');
});
