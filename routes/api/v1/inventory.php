<?php

use App\Http\Controllers\Api\V1\Inventory\BatchClassificationController;
use App\Http\Controllers\Api\V1\Inventory\SplitValuationController;
use App\Http\Controllers\Api\V1\Inventory\BatchWhereUsedController;
use App\Http\Controllers\Api\V1\Inventory\CategoryController;
use App\Http\Controllers\Api\V1\Inventory\CycleCountController;
use App\Http\Controllers\Api\V1\Inventory\CrossDockingController;
use App\Http\Controllers\Api\V1\Inventory\GoodsIssueController;
use App\Http\Controllers\Api\V1\Inventory\HazmatController;
use App\Http\Controllers\Api\V1\Inventory\MovementTypeController;
use App\Http\Controllers\Api\V1\Inventory\PickingListController;
use App\Http\Controllers\Api\V1\Inventory\ProductController;
use App\Http\Controllers\Api\V1\Inventory\SerialNumberController;
use App\Http\Controllers\Api\V1\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\V1\Inventory\StockController;
use App\Http\Controllers\Api\V1\Inventory\StockTransferController;
use App\Http\Controllers\Api\V1\Inventory\StorageTypeController;
use App\Http\Controllers\Api\V1\Inventory\WarehouseController;
use App\Http\Controllers\Api\V1\Inventory\YardManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/inventory
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('check.permission:inventory.categories.view')->name('inventory.categories.index');
        Route::post('/', [CategoryController::class, 'store'])->middleware('check.permission:inventory.categories.create')->name('inventory.categories.store');
        Route::get('/{category}', [CategoryController::class, 'show'])->middleware('check.permission:inventory.categories.view')->name('inventory.categories.show');
        Route::put('/{category}', [CategoryController::class, 'update'])->middleware('check.permission:inventory.categories.edit')->name('inventory.categories.update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('check.permission:inventory.categories.delete')->name('inventory.categories.destroy');
        Route::post('/{category}/move', [CategoryController::class, 'move'])->middleware('check.permission:inventory.categories.edit')->name('inventory.categories.move');
    });

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('check.permission:inventory.products.view')->name('inventory.products.index');
        Route::post('/', [ProductController::class, 'store'])->middleware('check.permission:inventory.products.create')->name('inventory.products.store');
        Route::get('/reorder-list', [ProductController::class, 'reorderList'])->middleware('check.permission:inventory.products.view')->name('inventory.products.reorder-list');
        Route::post('/bulk-update-prices', [ProductController::class, 'bulkUpdatePrices'])->middleware('check.permission:inventory.products.edit')->name('inventory.products.bulk-update-prices');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('check.permission:inventory.products.view')->name('inventory.products.show');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('check.permission:inventory.products.edit')->name('inventory.products.update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('check.permission:inventory.products.delete')->name('inventory.products.destroy');
        Route::get('/{product}/stock', [ProductController::class, 'stock'])->middleware('check.permission:inventory.products.view')->name('inventory.products.stock');
        Route::post('/{product}/clone', [ProductController::class, 'clone'])->middleware('check.permission:inventory.products.create')->name('inventory.products.clone');
    });

    /*
    |--------------------------------------------------------------------------
    | Warehouses
    |--------------------------------------------------------------------------
    */
    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->middleware('check.permission:inventory.warehouses.view')->name('inventory.warehouses.index');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('check.permission:inventory.warehouses.create')->name('inventory.warehouses.store');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('check.permission:inventory.warehouses.view')->name('inventory.warehouses.show');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('check.permission:inventory.warehouses.edit')->name('inventory.warehouses.update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('check.permission:inventory.warehouses.delete')->name('inventory.warehouses.destroy');
        Route::get('/{warehouse}/stock-valuation', [WarehouseController::class, 'stockValuation'])->middleware('check.permission:inventory.warehouses.view')->name('inventory.warehouses.stock-valuation');
        Route::get('/{warehouse}/low-stock', [WarehouseController::class, 'lowStock'])->middleware('check.permission:inventory.warehouses.view')->name('inventory.warehouses.low-stock');
        Route::post('/{warehouse}/set-default', [WarehouseController::class, 'setDefault'])->middleware('check.permission:inventory.warehouses.edit')->name('inventory.warehouses.set-default');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock
    |--------------------------------------------------------------------------
    */
    Route::prefix('stock')->group(function () {
        Route::get('/levels', [StockController::class, 'levels'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.levels');
        Route::get('/movements', [StockController::class, 'movements'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.movements');
        Route::get('/valuation', [StockController::class, 'valuation'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.valuation');
        Route::get('/low-stock', [StockController::class, 'lowStock'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.low-stock');
        Route::post('/check-availability', [StockController::class, 'checkAvailability'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.check-availability');
        Route::post('/reserve', [StockController::class, 'reserve'])->middleware('check.permission:inventory.stock.reserve')->name('inventory.stock.reserve');
        Route::post('/release', [StockController::class, 'release'])->middleware('check.permission:inventory.stock.reserve')->name('inventory.stock.release');
        Route::get('/reorder-points', [StockController::class, 'reorderPoints'])->middleware('check.permission:inventory.stock.view')->name('inventory.stock.reorder-points');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Adjustments
    |--------------------------------------------------------------------------
    */
    Route::prefix('stock-adjustments')->group(function () {
        Route::get('/', [StockAdjustmentController::class, 'index'])->middleware('check.permission:inventory.stock-adjustments.view')->name('inventory.adjustments.index');
        Route::post('/', [StockAdjustmentController::class, 'store'])->middleware('check.permission:inventory.stock-adjustments.create')->name('inventory.adjustments.store');
        Route::post('/quick-adjust', [StockAdjustmentController::class, 'quickAdjust'])->middleware('check.permission:inventory.stock-adjustments.create')->name('inventory.adjustments.quick-adjust');
        Route::get('/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->middleware('check.permission:inventory.stock-adjustments.view')->name('inventory.adjustments.show');
        Route::put('/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->middleware('check.permission:inventory.stock-adjustments.edit')->name('inventory.adjustments.update');
        Route::post('/{stockAdjustment}/post', [StockAdjustmentController::class, 'post'])->middleware('check.permission:inventory.stock-adjustments.post')->name('inventory.adjustments.post');
        Route::post('/{stockAdjustment}/cancel', [StockAdjustmentController::class, 'cancel'])->middleware('check.permission:inventory.stock-adjustments.edit')->name('inventory.adjustments.cancel');
        Route::get('/{stockAdjustment}/summary', [StockAdjustmentController::class, 'summary'])->middleware('check.permission:inventory.stock-adjustments.view')->name('inventory.adjustments.summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Goods Issues
    |--------------------------------------------------------------------------
    */
    Route::prefix('goods-issues')->group(function () {
        Route::get('/', [GoodsIssueController::class, 'index'])->middleware('check.permission:inventory.goods-issues.view')->name('inventory.goods-issues.index');
        Route::post('/', [GoodsIssueController::class, 'store'])->middleware('check.permission:inventory.goods-issues.create')->name('inventory.goods-issues.store');
        Route::get('/{goodsIssue}', [GoodsIssueController::class, 'show'])->middleware('check.permission:inventory.goods-issues.view')->name('inventory.goods-issues.show');
        Route::put('/{goodsIssue}', [GoodsIssueController::class, 'update'])->middleware('check.permission:inventory.goods-issues.edit')->name('inventory.goods-issues.update');
        Route::post('/{goodsIssue}/post', [GoodsIssueController::class, 'post'])->middleware('check.permission:inventory.goods-issues.post')->name('inventory.goods-issues.post');
        Route::post('/{goodsIssue}/reverse', [GoodsIssueController::class, 'reverse'])->middleware('check.permission:inventory.goods-issues.reverse')->name('inventory.goods-issues.reverse');
    });

    /*
    |--------------------------------------------------------------------------
    | Hazmat / EHS
    |--------------------------------------------------------------------------
    */
    Route::prefix('hazmat')->name('inventory.hazmat.')->group(function () {
        Route::get('/classifications', [HazmatController::class, 'classifications'])->middleware('check.permission:inventory.hazmat.view')->name('classifications');
        Route::post('/classifications', [HazmatController::class, 'storeClassification'])->middleware('check.permission:inventory.hazmat.manage')->name('classifications.store');
        Route::get('/storage-classes', [HazmatController::class, 'storageClasses'])->middleware('check.permission:inventory.hazmat.view')->name('storage-classes');
        Route::post('/storage-classes', [HazmatController::class, 'storeStorageClass'])->middleware('check.permission:inventory.hazmat.manage')->name('storage-classes.store');
        Route::post('/compatibility-check', [HazmatController::class, 'compatibilityCheck'])->middleware('check.permission:inventory.hazmat.view')->name('compatibility-check');
        Route::get('/sds', [HazmatController::class, 'sdsIndex'])->middleware('check.permission:inventory.hazmat.view')->name('sds');
        Route::post('/sds', [HazmatController::class, 'sdsStore'])->middleware('check.permission:inventory.hazmat.manage')->name('sds.store');
        Route::get('/sds/{id}', [HazmatController::class, 'sdsShow'])->middleware('check.permission:inventory.hazmat.view')->name('sds.show');
        Route::get('/products/{productId}/sds', [HazmatController::class, 'sdsCurrentForProduct'])->middleware('check.permission:inventory.hazmat.view')->name('products.sds');
        Route::get('/products/{productId}/transport-regulations', [HazmatController::class, 'transportRegulations'])->middleware('check.permission:inventory.hazmat.view')->name('transport-regulations');
        Route::post('/transport-regulations', [HazmatController::class, 'storeTransportRegulation'])->middleware('check.permission:inventory.hazmat.manage')->name('transport-regulations.store');
        Route::post('/products/{productId}/classify', [HazmatController::class, 'classifyProduct'])->middleware('check.permission:inventory.hazmat.manage')->name('products.classify');
        Route::get('/hazardous-products', [HazmatController::class, 'hazardousProducts'])->middleware('check.permission:inventory.hazmat.view')->name('hazardous-products');
    });

    /*
    |--------------------------------------------------------------------------
    | Stock Transfers
    |--------------------------------------------------------------------------
    */
    Route::prefix('stock-transfers')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->middleware('check.permission:inventory.stock-transfers.view')->name('inventory.transfers.index');
        Route::post('/', [StockTransferController::class, 'store'])->middleware('check.permission:inventory.stock-transfers.create')->name('inventory.transfers.store');
        Route::get('/pending', [StockTransferController::class, 'pending'])->middleware('check.permission:inventory.stock-transfers.view')->name('inventory.transfers.pending');
        Route::get('/overdue', [StockTransferController::class, 'overdue'])->middleware('check.permission:inventory.stock-transfers.view')->name('inventory.transfers.overdue');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('check.permission:inventory.stock-transfers.view')->name('inventory.transfers.show');
        Route::put('/{stockTransfer}', [StockTransferController::class, 'update'])->middleware('check.permission:inventory.stock-transfers.edit')->name('inventory.transfers.update');
        Route::post('/{stockTransfer}/ship', [StockTransferController::class, 'ship'])->middleware('check.permission:inventory.stock-transfers.ship')->name('inventory.transfers.ship');
        Route::post('/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('check.permission:inventory.stock-transfers.receive')->name('inventory.transfers.receive');
        Route::post('/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('check.permission:inventory.stock-transfers.edit')->name('inventory.transfers.cancel');
        Route::get('/{stockTransfer}/summary', [StockTransferController::class, 'summary'])->middleware('check.permission:inventory.stock-transfers.view')->name('inventory.transfers.summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Cross-Docking
    |--------------------------------------------------------------------------
    */
    Route::prefix('cross-docking')->name('inventory.cross-docking.')->group(function () {
        Route::get('/', [CrossDockingController::class, 'index'])->middleware('check.permission:inventory.cross-docking.view')->name('index');
        Route::post('/', [CrossDockingController::class, 'store'])->middleware('check.permission:inventory.cross-docking.create')->name('store');
        Route::get('/opportunities', [CrossDockingController::class, 'opportunities'])->middleware('check.permission:inventory.cross-docking.view')->name('opportunities');
        Route::get('/{id}', [CrossDockingController::class, 'show'])->middleware('check.permission:inventory.cross-docking.view')->name('show');
        Route::put('/{id}', [CrossDockingController::class, 'update'])->middleware('check.permission:inventory.cross-docking.edit')->name('update');
        Route::delete('/{id}', [CrossDockingController::class, 'destroy'])->middleware('check.permission:inventory.cross-docking.delete')->name('destroy');
        Route::post('/{id}/start', [CrossDockingController::class, 'start'])->middleware('check.permission:inventory.cross-docking.manage')->name('start');
        Route::post('/{id}/complete', [CrossDockingController::class, 'complete'])->middleware('check.permission:inventory.cross-docking.manage')->name('complete');
        Route::post('/lines/{lineId}/transfer', [CrossDockingController::class, 'transferLine'])->middleware('check.permission:inventory.cross-docking.manage')->name('lines.transfer');
    });

    /*
    |--------------------------------------------------------------------------
    | Yard Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('yard')->name('inventory.yard.')->group(function () {
        Route::get('/zones', [YardManagementController::class, 'zones'])->middleware('check.permission:inventory.yard.view')->name('zones');
        Route::post('/zones', [YardManagementController::class, 'storeZone'])->middleware('check.permission:inventory.yard.manage')->name('zones.store');
        Route::get('/dock-doors/available', [YardManagementController::class, 'availableDocks'])->middleware('check.permission:inventory.yard.view')->name('dock-doors.available');
        Route::get('/dock-doors', [YardManagementController::class, 'dockDoors'])->middleware('check.permission:inventory.yard.view')->name('dock-doors');
        Route::post('/dock-doors', [YardManagementController::class, 'storeDockDoor'])->middleware('check.permission:inventory.yard.manage')->name('dock-doors.store');
        Route::put('/dock-doors/{id}', [YardManagementController::class, 'updateDockDoor'])->middleware('check.permission:inventory.yard.manage')->name('dock-doors.update');
        Route::get('/appointments', [YardManagementController::class, 'appointments'])->middleware('check.permission:inventory.yard.view')->name('appointments');
        Route::post('/appointments', [YardManagementController::class, 'storeAppointment'])->middleware('check.permission:inventory.yard.manage')->name('appointments.store');
        Route::get('/appointments/{id}', [YardManagementController::class, 'showAppointment'])->middleware('check.permission:inventory.yard.view')->name('appointments.show');
        Route::put('/appointments/{id}', [YardManagementController::class, 'updateAppointment'])->middleware('check.permission:inventory.yard.manage')->name('appointments.update');
        Route::post('/appointments/{id}/cancel', [YardManagementController::class, 'cancelAppointment'])->middleware('check.permission:inventory.yard.manage')->name('appointments.cancel');
        Route::post('/appointments/{id}/check-in', [YardManagementController::class, 'checkIn'])->middleware('check.permission:inventory.yard.manage')->name('appointments.check-in');
        Route::post('/appointments/{id}/assign-dock', [YardManagementController::class, 'assignDock'])->middleware('check.permission:inventory.yard.manage')->name('appointments.assign-dock');
        Route::post('/appointments/{id}/depart', [YardManagementController::class, 'depart'])->middleware('check.permission:inventory.yard.manage')->name('appointments.depart');
        Route::get('/schedule', [YardManagementController::class, 'dailySchedule'])->middleware('check.permission:inventory.yard.view')->name('schedule');
        Route::get('/status', [YardManagementController::class, 'yardStatus'])->middleware('check.permission:inventory.yard.view')->name('status');
    });

    /*
    |--------------------------------------------------------------------------
    | Batch Classification (MM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('batch-classes')->name('mm.batch-classes.')->group(function () {
        Route::get('/', [BatchClassificationController::class, 'index'])->name('index');
        Route::post('/', [BatchClassificationController::class, 'store'])->name('store');
        Route::get('/{id}', [BatchClassificationController::class, 'show'])->name('show');
        Route::put('/{id}', [BatchClassificationController::class, 'update'])->name('update');
        Route::delete('/{id}', [BatchClassificationController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/characteristics', [BatchClassificationController::class, 'addCharacteristic'])->name('chars.add');
        Route::get('/{id}/characteristics', [BatchClassificationController::class, 'getCharacteristics'])->name('chars');
    });

    Route::get('batch-classes/search-by-characteristic', [BatchClassificationController::class, 'searchByCharacteristic'])->name('mm.batch-classes.search-by-char');

    /*
    |--------------------------------------------------------------------------
    | Batch Where-Used & Classification Values (MM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('batches')->name('mm.batches.')->group(function () {
        Route::post('/{batchId}/classification-values', [BatchClassificationController::class, 'setBatchValues'])->name('values.set');
        Route::get('/{batchId}/classification-values', [BatchClassificationController::class, 'getBatchValues'])->name('values');
        Route::get('/{batchId}/where-used', [BatchWhereUsedController::class, 'getForBatch'])->name('where-used');
        Route::get('/{batchId}/where-used-tree', [BatchWhereUsedController::class, 'whereUsedTree'])->name('where-used-tree');
    });

    Route::post('batch-where-used/record', [BatchWhereUsedController::class, 'record'])->name('mm.batch-where-used.record');
    Route::get('batch-where-used/by-reference', [BatchWhereUsedController::class, 'searchByReference'])->name('mm.batch-where-used.by-reference');

    /*
    |--------------------------------------------------------------------------
    | Storage Type Determination (WM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('storage-types')->name('wm.storage-types.')->group(function (): void {
        Route::get('/', [StorageTypeController::class, 'index'])->name('index');
        Route::post('/', [StorageTypeController::class, 'store'])->name('store');
        Route::post('/determine', [StorageTypeController::class, 'determine'])->name('determine');
        Route::get('/{id}', [StorageTypeController::class, 'show'])->name('show');
        Route::put('/{id}', [StorageTypeController::class, 'update'])->name('update');
        Route::delete('/{id}', [StorageTypeController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/rules', [StorageTypeController::class, 'addRule'])->name('rules.add');
        Route::put('/{id}/rules/{ruleId}', [StorageTypeController::class, 'updateRule'])->name('rules.update');
        Route::delete('/{id}/rules/{ruleId}', [StorageTypeController::class, 'removeRule'])->name('rules.remove');
    });

    /*
    |--------------------------------------------------------------------------
    | Cycle Counting (WM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('cycle-counts')->name('inventory.cycle-counts.')->group(function (): void {
        Route::get('/plans', [CycleCountController::class, 'plans'])->name('plans.index');
        Route::post('/plans', [CycleCountController::class, 'storePlan'])->name('plans.store');
        Route::post('/sessions', [CycleCountController::class, 'createSession'])->name('sessions.store');
        Route::get('/sessions/{id}', [CycleCountController::class, 'showSession'])->name('sessions.show');
        Route::put('/sessions/{sessionId}/lines/{lineId}', [CycleCountController::class, 'recordCount'])->name('lines.update');
        Route::post('/sessions/{id}/post', [CycleCountController::class, 'postAdjustments'])->name('sessions.post');
        Route::get('/warehouses/{warehouseId}/abc', [CycleCountController::class, 'abcAnalysis'])->name('abc-analysis');
    });

    /*
    |--------------------------------------------------------------------------
    | Serial Number Management
    |--------------------------------------------------------------------------
    */
    Route::apiResource('serial-numbers', SerialNumberController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->names('inventory.serial-numbers');
    Route::post('serial-numbers/bulk-create', [SerialNumberController::class, 'bulkCreate'])
        ->name('inventory.serial-numbers.bulk-create');
    Route::post('serial-numbers/{serialNumber}/receive', [SerialNumberController::class, 'receive'])
        ->name('inventory.serial-numbers.receive');
    Route::post('serial-numbers/{serialNumber}/issue', [SerialNumberController::class, 'issue'])
        ->name('inventory.serial-numbers.issue');
    Route::post('serial-numbers/{serialNumber}/transfer', [SerialNumberController::class, 'transfer'])
        ->name('inventory.serial-numbers.transfer');
    Route::post('serial-numbers/{serialNumber}/scrap', [SerialNumberController::class, 'scrap'])
        ->name('inventory.serial-numbers.scrap');
    Route::get('serial-numbers/{serialNumber}/history', [SerialNumberController::class, 'history'])
        ->name('inventory.serial-numbers.history');

    /*
    |--------------------------------------------------------------------------
    | Picking Lists (SAP LT0A / LT01 — warehouse picking)
    | Standalone picking list view per warehouse / source document.
    | Full wave-based picking is available under /inventory/warehouse-mgmt.
    |--------------------------------------------------------------------------
    */
    Route::prefix('picking-lists')->name('inventory.picking-lists.')->group(function () {
        Route::get('/', [PickingListController::class, 'index'])->middleware('check.permission:inventory.picking-lists.view')->name('index');
        Route::post('/', [PickingListController::class, 'store'])->middleware('check.permission:inventory.picking-lists.create')->name('store');
        Route::get('{uuid}', [PickingListController::class, 'show'])->middleware('check.permission:inventory.picking-lists.view')->name('show');
        Route::post('{uuid}/confirm', [PickingListController::class, 'confirmPick'])->middleware('check.permission:inventory.picking-lists.pick')->name('confirm');
    });

    /*
    |--------------------------------------------------------------------------
    | Movement Types (SAP OMJJ — movement type configuration & statistics)
    |--------------------------------------------------------------------------
    */
    Route::get('movement-types', [MovementTypeController::class, 'index'])->middleware('check.permission:inventory.stock.view')->name('inventory.movement-types.index');
    Route::get('movement-types/statistics', [MovementTypeController::class, 'statistics'])->middleware('check.permission:inventory.stock.view')->name('inventory.movement-types.statistics');

    /*
    |--------------------------------------------------------------------------
    | Split Valuation — SAP MM (MR21/MR22)
    |--------------------------------------------------------------------------
    */
    Route::prefix('split-valuation')->name('inventory.split-valuation.')->group(function (): void {
        Route::get('/', [SplitValuationController::class, 'index'])->name('index');
        Route::post('/goods-receipt', [SplitValuationController::class, 'goodsReceipt'])->name('goods-receipt');
        Route::post('/goods-issue', [SplitValuationController::class, 'goodsIssue'])->name('goods-issue');
        Route::post('/revaluate', [SplitValuationController::class, 'revaluate'])->name('revaluate');
        Route::post('/categories', [SplitValuationController::class, 'createCategory'])->name('categories.store');
        Route::post('/categories/{category}/types', [SplitValuationController::class, 'createType'])->name('types.store');
    });
});
