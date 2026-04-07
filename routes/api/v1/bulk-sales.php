<?php

use App\Http\Controllers\Api\V1\Sales\BackdatedTransactionController;
use App\Http\Controllers\Api\V1\Sales\BulkSaleController;
use App\Http\Controllers\Api\V1\Sales\QuickSaleTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bulk Sales API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/sales
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Bulk Sale Batches
    |--------------------------------------------------------------------------
    */
    Route::prefix('bulk-sales')->group(function () {
        Route::get('/', [BulkSaleController::class, 'index'])->name('sales.bulk-sales.index');
        Route::post('/', [BulkSaleController::class, 'store'])->name('sales.bulk-sales.store');
        Route::get('/stats', [BulkSaleController::class, 'stats'])->name('sales.bulk-sales.stats');
        Route::get('/{bulkSaleBatch}', [BulkSaleController::class, 'show'])->name('sales.bulk-sales.show');
        Route::put('/{bulkSaleBatch}', [BulkSaleController::class, 'update'])->name('sales.bulk-sales.update');
        Route::delete('/{bulkSaleBatch}', [BulkSaleController::class, 'destroy'])->name('sales.bulk-sales.destroy');
        Route::post('/{bulkSaleBatch}/process', [BulkSaleController::class, 'process'])->name('sales.bulk-sales.process');
        Route::post('/{bulkSaleBatch}/cancel', [BulkSaleController::class, 'cancel'])->name('sales.bulk-sales.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Quick Sale Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('quick-sale-templates')->group(function () {
        Route::get('/', [QuickSaleTemplateController::class, 'index'])->name('sales.quick-sale-templates.index');
        Route::post('/', [QuickSaleTemplateController::class, 'store'])->name('sales.quick-sale-templates.store');
        Route::get('/{quickSaleTemplate}', [QuickSaleTemplateController::class, 'show'])->name('sales.quick-sale-templates.show');
        Route::put('/{quickSaleTemplate}', [QuickSaleTemplateController::class, 'update'])->name('sales.quick-sale-templates.update');
        Route::delete('/{quickSaleTemplate}', [QuickSaleTemplateController::class, 'destroy'])->name('sales.quick-sale-templates.destroy');
        Route::get('/{quickSaleTemplate}/use', [QuickSaleTemplateController::class, 'use'])->name('sales.quick-sale-templates.use');
        Route::post('/{quickSaleTemplate}/duplicate', [QuickSaleTemplateController::class, 'duplicate'])->name('sales.quick-sale-templates.duplicate');
    });

    /*
    |--------------------------------------------------------------------------
    | Backdated Transactions
    |--------------------------------------------------------------------------
    */
    Route::prefix('backdated-transactions')->group(function () {
        Route::get('/', [BackdatedTransactionController::class, 'index'])->name('sales.backdated-transactions.index');
        Route::post('/', [BackdatedTransactionController::class, 'store'])->name('sales.backdated-transactions.store');
        Route::post('/validate-date', [BackdatedTransactionController::class, 'validateDate'])->name('sales.backdated-transactions.validate-date');
        Route::get('/{backdatedTransaction}', [BackdatedTransactionController::class, 'show'])->name('sales.backdated-transactions.show');
        Route::post('/{backdatedTransaction}/approve', [BackdatedTransactionController::class, 'approve'])->name('sales.backdated-transactions.approve');
        Route::post('/{backdatedTransaction}/reject', [BackdatedTransactionController::class, 'reject'])->name('sales.backdated-transactions.reject');
    });
});
