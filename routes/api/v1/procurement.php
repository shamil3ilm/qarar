<?php

use App\Http\Controllers\Api\V1\Purchase\ContractController;
use App\Http\Controllers\Api\V1\Purchase\GoodsReceiptController;
use App\Http\Controllers\Api\V1\Purchase\RfqController;
use App\Http\Controllers\Api\V1\Purchase\VendorAdvanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/procurement
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | RFQ / Request for Quotation
    |--------------------------------------------------------------------------
    */
    Route::prefix('rfq')->group(function () {
        Route::get('/', [RfqController::class, 'index'])
            ->middleware('check.permission:purchase.rfq.view')
            ->name('purchase.rfq.index');

        Route::post('/', [RfqController::class, 'store'])
            ->middleware('check.permission:purchase.rfq.create')
            ->name('purchase.rfq.store');

        Route::get('/{rfq}', [RfqController::class, 'show'])
            ->middleware('check.permission:purchase.rfq.view')
            ->name('purchase.rfq.show');

        Route::put('/{rfq}', [RfqController::class, 'update'])
            ->middleware('check.permission:purchase.rfq.edit')
            ->name('purchase.rfq.update');

        Route::post('/{rfq}/send-to-vendors', [RfqController::class, 'sendToVendors'])
            ->middleware('check.permission:purchase.rfq.send')
            ->name('purchase.rfq.send-to-vendors');

        Route::post('/{rfq}/record-quote', [RfqController::class, 'recordQuote'])
            ->middleware('check.permission:purchase.rfq.quote')
            ->name('purchase.rfq.record-quote');

        Route::get('/{rfq}/compare-quotes', [RfqController::class, 'compareQuotes'])
            ->middleware('check.permission:purchase.rfq.view')
            ->name('purchase.rfq.compare-quotes');

        Route::post('/{rfq}/award', [RfqController::class, 'awardQuote'])
            ->middleware('check.permission:purchase.rfq.award')
            ->name('purchase.rfq.award');

        Route::post('/{rfq}/convert-to-po', [RfqController::class, 'convertToPo'])
            ->middleware('check.permission:purchase.rfq.convert')
            ->name('purchase.rfq.convert-to-po');
    });

    /*
    |--------------------------------------------------------------------------
    | Contract Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index'])
            ->middleware('check.permission:purchase.contracts.view')
            ->name('purchase.contracts.index');

        Route::post('/', [ContractController::class, 'store'])
            ->middleware('check.permission:purchase.contracts.create')
            ->name('purchase.contracts.store');

        Route::get('/expiring', [ContractController::class, 'expiringContracts'])
            ->middleware('check.permission:purchase.contracts.view')
            ->name('purchase.contracts.expiring');

        Route::get('/{contract}', [ContractController::class, 'show'])
            ->middleware('check.permission:purchase.contracts.view')
            ->name('purchase.contracts.show');

        Route::put('/{contract}', [ContractController::class, 'update'])
            ->middleware('check.permission:purchase.contracts.edit')
            ->name('purchase.contracts.update');

        Route::delete('/{contract}', [ContractController::class, 'destroy'])
            ->middleware('check.permission:purchase.contracts.delete')
            ->name('purchase.contracts.destroy');

        Route::post('/{contract}/activate', [ContractController::class, 'activate'])
            ->middleware('check.permission:purchase.contracts.activate')
            ->name('purchase.contracts.activate');

        Route::post('/{contract}/terminate', [ContractController::class, 'terminate'])
            ->middleware('check.permission:purchase.contracts.terminate')
            ->name('purchase.contracts.terminate');

        Route::post('/{contract}/releases', [ContractController::class, 'createRelease'])
            ->middleware('check.permission:purchase.contracts.release')
            ->name('purchase.contracts.releases.store');

        Route::get('/{contract}/releases', [ContractController::class, 'indexReleases'])
            ->middleware('check.permission:purchase.contracts.view')
            ->name('purchase.contracts.releases.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Goods Receipts (3-Way Match)
    |--------------------------------------------------------------------------
    */
    Route::prefix('goods-receipts')->group(function () {
        Route::get('/', [GoodsReceiptController::class, 'index'])
            ->middleware('check.permission:purchase.gr.view')
            ->name('purchase.gr.index');

        Route::post('/', [GoodsReceiptController::class, 'store'])
            ->middleware('check.permission:purchase.gr.create')
            ->name('purchase.gr.store');

        Route::get('/three-way-match', [GoodsReceiptController::class, 'threeWayMatch'])
            ->middleware('check.permission:purchase.gr.view')
            ->name('purchase.gr.three-way-match');

        Route::get('/{goodsReceipt}', [GoodsReceiptController::class, 'show'])
            ->middleware('check.permission:purchase.gr.view')
            ->name('purchase.gr.show');

        Route::post('/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])
            ->middleware('check.permission:purchase.gr.post')
            ->name('purchase.gr.post');

        Route::post('/{goodsReceipt}/reverse', [GoodsReceiptController::class, 'reverse'])
            ->middleware('check.permission:purchase.gr.reverse')
            ->name('purchase.gr.reverse');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Advance Payments
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-advances')->group(function () {
        Route::get('/', [VendorAdvanceController::class, 'index'])
            ->middleware('check.permission:purchase.vendor-advances.view')
            ->name('purchase.vendor-advances.index');

        Route::post('/', [VendorAdvanceController::class, 'store'])
            ->middleware('check.permission:purchase.vendor-advances.create')
            ->name('purchase.vendor-advances.store');

        Route::post('/clear', [VendorAdvanceController::class, 'clear'])
            ->middleware('check.permission:purchase.vendor-advances.clear')
            ->name('purchase.vendor-advances.clear');

        Route::get('/{vendorAdvance}', [VendorAdvanceController::class, 'show'])
            ->middleware('check.permission:purchase.vendor-advances.view')
            ->name('purchase.vendor-advances.show');

        Route::post('/{vendorAdvance}/approve', [VendorAdvanceController::class, 'approve'])
            ->middleware('check.permission:purchase.vendor-advances.approve')
            ->name('purchase.vendor-advances.approve');

        Route::post('/{vendorAdvance}/payment', [VendorAdvanceController::class, 'recordPayment'])
            ->middleware('check.permission:purchase.vendor-advances.pay')
            ->name('purchase.vendor-advances.payment');

        Route::get('/{vendorAdvance}/clearings', [VendorAdvanceController::class, 'indexClearings'])
            ->middleware('check.permission:purchase.vendor-advances.view')
            ->name('purchase.vendor-advances.clearings');
    });
});
