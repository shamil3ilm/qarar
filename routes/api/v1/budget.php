<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Budget\BudgetController;
use App\Http\Controllers\Api\V1\Budget\BudgetTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('budgets')->group(function () {
    Route::get('/', [BudgetController::class, 'index'])->middleware('check.permission:budget.budgets.view');
    Route::post('/', [BudgetController::class, 'store'])->middleware('check.permission:budget.budgets.create');
    Route::get('/vs-actual', [BudgetController::class, 'vsActual'])->middleware('check.permission:budget.budgets.view');
    Route::get('/{id}', [BudgetController::class, 'show'])->middleware('check.permission:budget.budgets.view');
    Route::put('/{id}', [BudgetController::class, 'update'])->middleware('check.permission:budget.budgets.edit');
    Route::delete('/{id}', [BudgetController::class, 'destroy'])->middleware('check.permission:budget.budgets.delete');
    Route::post('/{id}/submit', [BudgetController::class, 'submit'])->middleware('check.permission:budget.budgets.edit');
    Route::post('/{id}/approve', [BudgetController::class, 'approve'])->middleware('check.permission:budget.budgets.approve');
    Route::post('/{id}/activate', [BudgetController::class, 'activate'])->middleware('check.permission:budget.budgets.edit');
    Route::post('/{id}/lines', [BudgetController::class, 'storeLine'])->middleware('check.permission:budget.budgets.edit');
    Route::put('/{id}/lines/{lineId}', [BudgetController::class, 'updateLine'])->middleware('check.permission:budget.budgets.edit');
    Route::delete('/{id}/lines/{lineId}', [BudgetController::class, 'destroyLine'])->middleware('check.permission:budget.budgets.edit');
    Route::post('/{id}/revisions', [BudgetController::class, 'storeRevision'])->middleware('check.permission:budget.budgets.edit');
    Route::get('/{id}/commitments', [BudgetController::class, 'commitments'])->middleware('check.permission:budget.budgets.view');
});

// Budget Transfers — SAP FM budget reallocation (FM2S)
Route::prefix('budget-transfers')->group(function () {
    Route::get('/', [BudgetTransferController::class, 'index'])->middleware('check.permission:budget.transfers.view');
    Route::post('/', [BudgetTransferController::class, 'store'])->middleware('check.permission:budget.transfers.create');
    Route::get('/{id}', [BudgetTransferController::class, 'show'])->middleware('check.permission:budget.transfers.view');
    Route::post('/{id}/submit', [BudgetTransferController::class, 'submit'])->middleware('check.permission:budget.transfers.edit');
    Route::post('/{id}/review', [BudgetTransferController::class, 'review'])->middleware('check.permission:budget.transfers.approve');
});
