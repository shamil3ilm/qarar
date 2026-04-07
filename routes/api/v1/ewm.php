<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\EwmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EWM — Extended Warehouse Management (SAP EWM equivalent)
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/inventory/ewm (see routes/api.php).
|
| SAP TCode references:
|   LS01N  — Create storage bin
|   LT01   — Create transfer order
|   LT0A   — Create TO from delivery
|   /SCWM/TRTASK — Labor task management
|   L09A   — Putaway strategy determination
|
*/

// Storage Types (EWM storage type master data)
Route::prefix('storage-types')->group(function () {
    Route::get('/', [EwmController::class, 'indexStorageTypes'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.storage-types.index');

    Route::post('/', [EwmController::class, 'storeStorageType'])
        ->middleware('check.permission:inventory.ewm.manage')
        ->name('inventory.ewm.storage-types.store');
});

// Bin Management (SAP LS01N / LSMW)
Route::prefix('bins')->group(function () {
    Route::get('/', [EwmController::class, 'indexBins'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.bins.index');

    Route::post('/', [EwmController::class, 'storeBin'])
        ->middleware('check.permission:inventory.ewm.manage')
        ->name('inventory.ewm.bins.store');

    // Note: static segment must precede dynamic {uuid}
    Route::get('putaway-suggestion', [EwmController::class, 'findPutawayBin'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.bins.putaway-suggestion');

    Route::get('{uuid}', [EwmController::class, 'showBin'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.bins.show');

    Route::post('{uuid}/status', [EwmController::class, 'updateBinStatus'])
        ->middleware('check.permission:inventory.ewm.manage')
        ->name('inventory.ewm.bins.update-status');
});

// Transfer Orders (SAP LT01 / LT0A)
Route::prefix('transfer-orders')->group(function () {
    Route::get('/', [EwmController::class, 'indexTransferOrders'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.transfer-orders.index');

    Route::post('/', [EwmController::class, 'storeTransferOrder'])
        ->middleware('check.permission:inventory.ewm.transfer-orders.create')
        ->name('inventory.ewm.transfer-orders.store');

    Route::get('{uuid}', [EwmController::class, 'showTransferOrder'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.transfer-orders.show');

    Route::post('{uuid}/confirm', [EwmController::class, 'confirmTransferOrder'])
        ->middleware('check.permission:inventory.ewm.transfer-orders.confirm')
        ->name('inventory.ewm.transfer-orders.confirm');

    Route::post('{uuid}/cancel', [EwmController::class, 'cancelTransferOrder'])
        ->middleware('check.permission:inventory.ewm.transfer-orders.cancel')
        ->name('inventory.ewm.transfer-orders.cancel');
});

// Labor Management (SAP EWM /SCWM/TRTASK)
Route::prefix('labor')->group(function () {
    Route::get('dashboard', [EwmController::class, 'laborDashboard'])
        ->middleware('check.permission:inventory.ewm.view')
        ->name('inventory.ewm.labor.dashboard');

    Route::post('tasks/{uuid}/assign', [EwmController::class, 'assignTask'])
        ->middleware('check.permission:inventory.ewm.labor.manage')
        ->name('inventory.ewm.labor.tasks.assign');

    Route::post('tasks/{uuid}/start', [EwmController::class, 'startTask'])
        ->middleware('check.permission:inventory.ewm.labor.manage')
        ->name('inventory.ewm.labor.tasks.start');
});

// Reports & Configuration
Route::get('bin-utilization', [EwmController::class, 'binUtilization'])
    ->middleware('check.permission:inventory.ewm.view')
    ->name('inventory.ewm.bin-utilization');

Route::get('putaway-rules', [EwmController::class, 'putawayRules'])
    ->middleware('check.permission:inventory.ewm.view')
    ->name('inventory.ewm.putaway-rules.index');

Route::post('putaway-rules', [EwmController::class, 'storePutawayRule'])
    ->middleware('check.permission:inventory.ewm.manage')
    ->name('inventory.ewm.putaway-rules.store');
