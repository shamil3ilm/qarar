<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Purchase\PurchaseRequisitionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('requisitions', PurchaseRequisitionController::class)
    ->names('purchase.requisitions');

Route::post('requisitions/{purchaseRequisition}/submit', [PurchaseRequisitionController::class, 'submit'])
    ->name('purchase.requisitions.submit');

Route::post('requisitions/{purchaseRequisition}/approve', [PurchaseRequisitionController::class, 'approve'])
    ->name('purchase.requisitions.approve');

Route::post('requisitions/{purchaseRequisition}/convert-to-po', [PurchaseRequisitionController::class, 'convertToPO'])
    ->name('purchase.requisitions.convert-to-po');

Route::post('requisitions/{purchaseRequisition}/cancel', [PurchaseRequisitionController::class, 'cancel'])
    ->name('purchase.requisitions.cancel');
