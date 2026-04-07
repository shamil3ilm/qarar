<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\IntercompanySalesController;

Route::prefix('intercompany/sales-orders')->name('ic.sales-orders.')->group(function () {
    Route::get('/', [IntercompanySalesController::class, 'index'])->name('index');
    Route::post('/', [IntercompanySalesController::class, 'store'])->name('store');
    Route::get('/{id}', [IntercompanySalesController::class, 'show'])->name('show');
    Route::put('/{id}', [IntercompanySalesController::class, 'update'])->name('update');
    Route::post('/{id}/confirm', [IntercompanySalesController::class, 'confirm'])->name('confirm');
    Route::post('/{id}/link-purchase-order', [IntercompanySalesController::class, 'linkPurchaseOrder'])->name('link-po');
    Route::post('/{id}/start-delivery', [IntercompanySalesController::class, 'startDelivery'])->name('start-delivery');
    Route::post('/{id}/billing-documents', [IntercompanySalesController::class, 'createBillingDocument'])->name('billing.create');
    Route::post('/{id}/billing-documents/{billingDocId}/post', [IntercompanySalesController::class, 'postBillingDocument'])->name('billing.post');
    Route::post('/{id}/cancel', [IntercompanySalesController::class, 'cancel'])->name('cancel');
});
