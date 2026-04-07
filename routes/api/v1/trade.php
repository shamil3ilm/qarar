<?php

use App\Http\Controllers\Api\V1\Trade\IncotermController;
use App\Http\Controllers\Api\V1\Trade\TradeDocumentController;
use App\Http\Controllers\Api\V1\Trade\LetterOfCreditController;
use App\Http\Controllers\Api\V1\Trade\ImportExportShipmentController;
use App\Http\Controllers\Api\V1\Trade\LandedCostController;
use App\Http\Controllers\Api\V1\Trade\TradeAgreementController;
use Illuminate\Support\Facades\Route;

// Incoterms (reference data — read-only, no write permission needed)
Route::prefix('incoterms')->group(function () {
    Route::get('/', [IncotermController::class, 'index']);
    Route::get('/{incoterm}', [IncotermController::class, 'show']);
});

// Trade Documents
Route::prefix('trade-documents')->group(function () {
    Route::get('/', [TradeDocumentController::class, 'index'])->middleware('check.permission:trade.trade-documents.view');
    Route::post('/', [TradeDocumentController::class, 'store'])->middleware('check.permission:trade.trade-documents.create');
    Route::get('/{tradeDocument}', [TradeDocumentController::class, 'show'])->middleware('check.permission:trade.trade-documents.view');
    Route::put('/{tradeDocument}', [TradeDocumentController::class, 'update'])->middleware('check.permission:trade.trade-documents.edit');
    Route::delete('/{tradeDocument}', [TradeDocumentController::class, 'destroy'])->middleware('check.permission:trade.trade-documents.delete');
    Route::post('/{tradeDocument}/attach', [TradeDocumentController::class, 'attachFile'])->middleware('check.permission:trade.trade-documents.edit');
});

// Letters of Credit
Route::prefix('letters-of-credit')->group(function () {
    Route::get('/', [LetterOfCreditController::class, 'index'])->middleware('check.permission:trade.letters-of-credit.view');
    Route::post('/', [LetterOfCreditController::class, 'store'])->middleware('check.permission:trade.letters-of-credit.create');
    Route::get('/{letterOfCredit}', [LetterOfCreditController::class, 'show'])->middleware('check.permission:trade.letters-of-credit.view');
    Route::put('/{letterOfCredit}', [LetterOfCreditController::class, 'update'])->middleware('check.permission:trade.letters-of-credit.edit');
    Route::delete('/{letterOfCredit}', [LetterOfCreditController::class, 'destroy'])->middleware('check.permission:trade.letters-of-credit.delete');
    Route::post('/{letterOfCredit}/issue', [LetterOfCreditController::class, 'issue'])->middleware('check.permission:trade.letters-of-credit.issue');
    Route::post('/{letterOfCredit}/amend', [LetterOfCreditController::class, 'amend'])->middleware('check.permission:trade.letters-of-credit.edit');
    Route::post('/{letterOfCredit}/utilize', [LetterOfCreditController::class, 'utilize'])->middleware('check.permission:trade.letters-of-credit.utilize');
    Route::post('/{letterOfCredit}/close', [LetterOfCreditController::class, 'close'])->middleware('check.permission:trade.letters-of-credit.edit');
});

// Import/Export Shipments
Route::prefix('shipments')->group(function () {
    Route::get('/', [ImportExportShipmentController::class, 'index'])->middleware('check.permission:trade.shipments.view');
    Route::post('/', [ImportExportShipmentController::class, 'store'])->middleware('check.permission:trade.shipments.create');
    Route::get('/{importExportShipment}', [ImportExportShipmentController::class, 'show'])->middleware('check.permission:trade.shipments.view');
    Route::put('/{importExportShipment}', [ImportExportShipmentController::class, 'update'])->middleware('check.permission:trade.shipments.edit');
    Route::delete('/{importExportShipment}', [ImportExportShipmentController::class, 'destroy'])->middleware('check.permission:trade.shipments.delete');
    Route::post('/{importExportShipment}/status', [ImportExportShipmentController::class, 'updateStatus'])->middleware('check.permission:trade.shipments.edit');
    Route::post('/{importExportShipment}/items', [ImportExportShipmentController::class, 'addItems'])->middleware('check.permission:trade.shipments.edit');
    Route::post('/{importExportShipment}/link-customs', [ImportExportShipmentController::class, 'link'])->middleware('check.permission:trade.shipments.edit');
    Route::post('/{importExportShipment}/link-lc', [ImportExportShipmentController::class, 'link'])->middleware('check.permission:trade.shipments.edit');
});

// Landed Cost Vouchers
Route::prefix('landed-costs')->group(function () {
    Route::get('/', [LandedCostController::class, 'index'])->middleware('check.permission:trade.landed-costs.view');
    Route::post('/', [LandedCostController::class, 'store'])->middleware('check.permission:trade.landed-costs.create');
    Route::get('/{landedCostVoucher}', [LandedCostController::class, 'show'])->middleware('check.permission:trade.landed-costs.view');
    Route::put('/{landedCostVoucher}', [LandedCostController::class, 'update'])->middleware('check.permission:trade.landed-costs.edit');
    Route::delete('/{landedCostVoucher}', [LandedCostController::class, 'destroy'])->middleware('check.permission:trade.landed-costs.delete');
    Route::post('/{landedCostVoucher}/items', [LandedCostController::class, 'addItems'])->middleware('check.permission:trade.landed-costs.edit');
    Route::post('/{landedCostVoucher}/charges', [LandedCostController::class, 'addCharges'])->middleware('check.permission:trade.landed-costs.edit');
    Route::post('/{landedCostVoucher}/allocate', [LandedCostController::class, 'allocate'])->middleware('check.permission:trade.landed-costs.edit');
    Route::post('/{landedCostVoucher}/post', [LandedCostController::class, 'post'])->middleware('check.permission:trade.landed-costs.post');
});

// Trade Agreements
Route::prefix('trade-agreements')->group(function () {
    Route::get('/', [TradeAgreementController::class, 'index'])->middleware('check.permission:trade.trade-agreements.view');
    Route::post('/', [TradeAgreementController::class, 'store'])->middleware('check.permission:trade.trade-agreements.create');
    Route::get('/{tradeAgreement}', [TradeAgreementController::class, 'show'])->middleware('check.permission:trade.trade-agreements.view');
    Route::put('/{tradeAgreement}', [TradeAgreementController::class, 'update'])->middleware('check.permission:trade.trade-agreements.edit');
    Route::delete('/{tradeAgreement}', [TradeAgreementController::class, 'destroy'])->middleware('check.permission:trade.trade-agreements.delete');
    Route::get('/{tradeAgreement}/preferential-rates', [TradeAgreementController::class, 'preferentialRates'])->middleware('check.permission:trade.trade-agreements.view');
    Route::post('/{tradeAgreement}/preferential-rates', [TradeAgreementController::class, 'addPreferentialRates'])->middleware('check.permission:trade.trade-agreements.edit');
});
