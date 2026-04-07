<?php

use App\Http\Controllers\Api\V1\Manufacturing\SubcontractingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Subcontracting (SAP PP-SB) Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Subcontract Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('subcontract-orders')->group(function () {
        Route::get('/', [SubcontractingController::class, 'index'])
            ->middleware('check.permission:manufacturing.subcontracting.view');

        Route::post('/', [SubcontractingController::class, 'store'])
            ->middleware('check.permission:manufacturing.subcontracting.create');

        Route::get('/{subcontractOrder}', [SubcontractingController::class, 'show'])
            ->middleware('check.permission:manufacturing.subcontracting.view');

        Route::put('/{subcontractOrder}', [SubcontractingController::class, 'update'])
            ->middleware('check.permission:manufacturing.subcontracting.edit');

        // Status transitions
        Route::post('/{subcontractOrder}/send', [SubcontractingController::class, 'sendToVendor'])
            ->middleware('check.permission:manufacturing.subcontracting.edit');

        Route::post('/{subcontractOrder}/close', [SubcontractingController::class, 'closeOrder'])
            ->middleware('check.permission:manufacturing.subcontracting.close');

        Route::post('/{subcontractOrder}/cancel', [SubcontractingController::class, 'cancel'])
            ->middleware('check.permission:manufacturing.subcontracting.cancel');

        /*
        |----------------------------------------------------------------------
        | Material Transfers
        |----------------------------------------------------------------------
        */
        Route::post('/{subcontractOrder}/transfer-materials', [SubcontractingController::class, 'transferMaterials'])
            ->middleware('check.permission:manufacturing.subcontracting.transfer');

        Route::get('/{subcontractOrder}/transfers', [SubcontractingController::class, 'indexTransfers'])
            ->middleware('check.permission:manufacturing.subcontracting.view');

        /*
        |----------------------------------------------------------------------
        | Goods Receipts
        |----------------------------------------------------------------------
        */
        Route::post('/{subcontractOrder}/receive', [SubcontractingController::class, 'receiveFromVendor'])
            ->middleware('check.permission:manufacturing.subcontracting.receive');

        Route::get('/{subcontractOrder}/receipts', [SubcontractingController::class, 'indexReceipts'])
            ->middleware('check.permission:manufacturing.subcontracting.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Transfer Documents (standalone lookup)
    |--------------------------------------------------------------------------
    */
    Route::prefix('subcontract-transfers')->group(function () {
        Route::get('/{transfer}', [SubcontractingController::class, 'showTransfer'])
            ->middleware('check.permission:manufacturing.subcontracting.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Receipt Documents (standalone lookup)
    |--------------------------------------------------------------------------
    */
    Route::prefix('subcontract-receipts')->group(function () {
        Route::get('/{receipt}', [SubcontractingController::class, 'showReceipt'])
            ->middleware('check.permission:manufacturing.subcontracting.view');
    });
});
