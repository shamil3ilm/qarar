<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\TravelExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('travel')->group(function (): void {
    // Per Diem Rates
    Route::apiResource('per-diem-rates', TravelExpenseController::class)
        ->names('hr.per-diem-rates');

    Route::post('per-diem-rates/calculate', [TravelExpenseController::class, 'calculatePerDiem'])
        ->name('hr.per-diem.calculate');

    // Travel Requests
    Route::apiResource('requests', TravelExpenseController::class)
        ->names('hr.travel-requests');

    Route::post('requests/{travelRequest}/submit', [TravelExpenseController::class, 'submitRequest'])
        ->name('hr.travel-requests.submit');

    Route::post('requests/{travelRequest}/approve', [TravelExpenseController::class, 'approveRequest'])
        ->name('hr.travel-requests.approve');

    Route::post('requests/{travelRequest}/reject', [TravelExpenseController::class, 'rejectRequest'])
        ->name('hr.travel-requests.reject');

    // Expense Claims
    Route::apiResource('claims', TravelExpenseController::class)
        ->names('hr.travel-claims');

    Route::post('claims/{travelExpenseClaim}/lines', [TravelExpenseController::class, 'addLine'])
        ->name('hr.travel-claims.add-line');

    Route::post('claims/{travelExpenseClaim}/submit', [TravelExpenseController::class, 'submitClaim'])
        ->name('hr.travel-claims.submit');

    Route::post('claims/{travelExpenseClaim}/approve', [TravelExpenseController::class, 'approveClaim'])
        ->name('hr.travel-claims.approve');

    Route::post('claims/{travelExpenseClaim}/process', [TravelExpenseController::class, 'processClaim'])
        ->name('hr.travel-claims.process');
});
