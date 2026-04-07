<?php

use App\Http\Controllers\Api\V1\Manufacturing\AuditManagementController;
use App\Http\Controllers\Api\V1\Manufacturing\BomAlternativeController;
use App\Http\Controllers\Api\V1\Manufacturing\Capa8DController;
use App\Http\Controllers\Api\V1\Manufacturing\CapaController;
use App\Http\Controllers\Api\V1\Manufacturing\DynamicModificationController;
use App\Http\Controllers\Api\V1\Manufacturing\ComplaintController;
use App\Http\Controllers\Api\V1\Manufacturing\SupplierQualityController;
use App\Http\Controllers\Api\V1\Manufacturing\BomController;
use App\Http\Controllers\Api\V1\Manufacturing\CoProductController;
use App\Http\Controllers\Api\V1\Manufacturing\EngineeringChangeController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductionResourceToolController;
use App\Http\Controllers\Api\V1\Manufacturing\ReturnsInspectionController;
use App\Http\Controllers\Api\V1\Manufacturing\ScrapReportingController;
use App\Http\Controllers\Api\V1\Manufacturing\CalibrationController;
use App\Http\Controllers\Api\V1\Manufacturing\CapacityController;
use App\Http\Controllers\Api\V1\Manufacturing\CapacityLevelingController;
use App\Http\Controllers\Api\V1\Manufacturing\DetailedSchedulingController;
use App\Http\Controllers\Api\V1\Manufacturing\LongTermPlanningController;
use App\Http\Controllers\Api\V1\Manufacturing\MrpController;
use App\Http\Controllers\Api\V1\Manufacturing\ProcessOrderController;
use App\Http\Controllers\Api\V1\Manufacturing\ProcurementInspectionController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductCostCollectorController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductionVersionController;
use App\Http\Controllers\Api\V1\Manufacturing\RepetitiveManufacturingController;
use App\Http\Controllers\Api\V1\Manufacturing\SpcController;
use App\Http\Controllers\Api\V1\Manufacturing\WorkOrderController;
use App\Http\Controllers\Api\V1\Manufacturing\QInfoRecordController;
use App\Http\Controllers\Api\V1\Manufacturing\QualityCostController;
use App\Http\Controllers\Api\V1\Manufacturing\SkipLotController;
use App\Http\Controllers\Api\V1\Manufacturing\StabilityStudyController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenancePermitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | BOM Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('bom-templates')->group(function () {
        Route::get('/', [BomController::class, 'index'])->middleware('check.permission:manufacturing.bom.view');
        Route::post('/', [BomController::class, 'store'])->middleware('check.permission:manufacturing.bom.create');
        Route::get('/for-product', [BomController::class, 'forProduct'])->middleware('check.permission:manufacturing.bom.view');
        Route::get('/{bom}', [BomController::class, 'show'])->middleware('check.permission:manufacturing.bom.view');
        Route::put('/{bom}', [BomController::class, 'update'])->middleware('check.permission:manufacturing.bom.edit');
        Route::delete('/{bom}', [BomController::class, 'destroy'])->middleware('check.permission:manufacturing.bom.delete');

        // Actions
        Route::patch('/{bom}/active', [BomController::class, 'setActive'])->middleware('check.permission:manufacturing.bom.edit');
        Route::post('/{bom}/duplicate', [BomController::class, 'duplicate'])->middleware('check.permission:manufacturing.bom.create');

        // Analysis
        Route::get('/{bom}/cost-breakdown', [BomController::class, 'costBreakdown'])->middleware('check.permission:manufacturing.bom.view');
        Route::get('/{bom}/check-availability', [BomController::class, 'checkAvailability'])->middleware('check.permission:manufacturing.bom.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-orders')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->middleware('check.permission:manufacturing.workorders.view');
        Route::post('/', [WorkOrderController::class, 'store'])->middleware('check.permission:manufacturing.workorders.create');
        Route::get('/statistics', [WorkOrderController::class, 'statistics'])->middleware('check.permission:manufacturing.workorders.view');
        Route::get('/production-schedule', [WorkOrderController::class, 'productionSchedule'])->middleware('check.permission:manufacturing.workorders.view');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->middleware('check.permission:manufacturing.workorders.view');
        Route::put('/{workOrder}', [WorkOrderController::class, 'update'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::delete('/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('check.permission:manufacturing.workorders.delete');

        // Status transitions
        Route::post('/{workOrder}/release', [WorkOrderController::class, 'release'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::post('/{workOrder}/schedule', [WorkOrderController::class, 'schedule'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::post('/{workOrder}/start', [WorkOrderController::class, 'start'])->middleware('check.permission:manufacturing.workorders.start');
        Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete'])->middleware('check.permission:manufacturing.workorders.complete');
        Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->middleware('check.permission:manufacturing.workorders.cancel');

        // Material management
        Route::post('/{workOrder}/issue-materials', [WorkOrderController::class, 'issueMaterials'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/return-materials', [WorkOrderController::class, 'returnMaterials'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/consume-materials', [WorkOrderController::class, 'consumeMaterials'])->middleware('check.permission:manufacturing.workorders.produce');

        // Production
        Route::post('/{workOrder}/record-production', [WorkOrderController::class, 'recordProduction'])->middleware('check.permission:manufacturing.workorders.produce');

        // Operations
        Route::post('/{workOrder}/operations/{operation}/start', [WorkOrderController::class, 'startOperation'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/operations/{operation}/complete', [WorkOrderController::class, 'completeOperation'])->middleware('check.permission:manufacturing.workorders.produce');
    });

    /*
    |--------------------------------------------------------------------------
    | MRP (Material Requirements Planning)
    |--------------------------------------------------------------------------
    */
    Route::prefix('mrp')->name('manufacturing.mrp.')->group(function () {
        Route::get('/runs', [MrpController::class, 'index'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.index');
        Route::post('/runs', [MrpController::class, 'run'])->middleware('check.permission:manufacturing.mrp.run')->name('runs.run');
        Route::get('/runs/{id}', [MrpController::class, 'show'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.show');
        Route::get('/runs/{id}/planned-orders', [MrpController::class, 'plannedOrders'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.planned-orders');
        Route::post('/runs/{mrpRun}/convert-to-pr', [MrpController::class, 'convertToPR'])->middleware('check.permission:manufacturing.mrp.convert')->name('runs.convert-to-pr');
        Route::post('/planned-orders/{id}/firm', [MrpController::class, 'firmOrder'])->middleware('check.permission:manufacturing.mrp.manage')->name('planned-orders.firm');
        Route::post('/planned-orders/{id}/convert', [MrpController::class, 'convertOrder'])->middleware('check.permission:manufacturing.mrp.convert')->name('planned-orders.convert');
        Route::get('/exceptions', [MrpController::class, 'exceptions'])->middleware('check.permission:manufacturing.mrp.view')->name('exceptions');
        Route::get('/forecasts', [MrpController::class, 'forecasts'])->middleware('check.permission:manufacturing.mrp.view')->name('forecasts.index');
        Route::post('/forecasts', [MrpController::class, 'storeForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.store');
        Route::put('/forecasts/{id}', [MrpController::class, 'updateForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.update');
        Route::delete('/forecasts/{id}', [MrpController::class, 'destroyForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.destroy');
        Route::get('/forecast-accuracy', [MrpController::class, 'forecastAccuracy'])->middleware('check.permission:manufacturing.mrp.view')->name('forecast-accuracy');
        Route::post('/capacity-check', [MrpController::class, 'capacityCheck'])->middleware('check.permission:manufacturing.mrp.manage')->name('capacity-check');
        Route::get('/capacity-load', [MrpController::class, 'capacityLoad'])->middleware('check.permission:manufacturing.mrp.view')->name('capacity-load');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Centers & Capacity Planning
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-centers')->name('manufacturing.work-centers.')->group(function (): void {
        Route::get('/', [CapacityController::class, 'indexWorkCenters'])->middleware('check.permission:manufacturing.capacity.view')->name('index');
        Route::post('/', [CapacityController::class, 'storeWorkCenter'])->middleware('check.permission:manufacturing.capacity.manage')->name('store');
        Route::get('/{workCenter}', [CapacityController::class, 'showWorkCenter'])->middleware('check.permission:manufacturing.capacity.view')->name('show');
        Route::put('/{workCenter}', [CapacityController::class, 'updateWorkCenter'])->middleware('check.permission:manufacturing.capacity.manage')->name('update');
        Route::delete('/{workCenter}', [CapacityController::class, 'destroyWorkCenter'])->middleware('check.permission:manufacturing.capacity.manage')->name('destroy');
        Route::get('/{workCenter}/load', [CapacityController::class, 'workCenterLoad'])->middleware('check.permission:manufacturing.capacity.view')->name('load');
        Route::post('/{workCenter}/exceptions', [CapacityController::class, 'storeException'])->middleware('check.permission:manufacturing.capacity.manage')->name('exceptions.store');
    });

    Route::prefix('capacity')->name('manufacturing.capacity.')->group(function (): void {
        Route::get('/load', [CapacityController::class, 'capacityLoad'])->middleware('check.permission:manufacturing.capacity.view')->name('load');
        Route::get('/utilization', [CapacityController::class, 'utilizationReport'])->middleware('check.permission:manufacturing.capacity.view')->name('utilization');
        Route::get('/requirements', [CapacityController::class, 'capacityRequirements'])->middleware('check.permission:manufacturing.capacity.view')->name('requirements');
        Route::post('/refresh', [CapacityController::class, 'refreshCapacityLoad'])->middleware('check.permission:manufacturing.capacity.manage')->name('refresh');
    });

    Route::prefix('capacity-leveling')->name('manufacturing.capacity-leveling.')->group(function (): void {
        Route::get('/suggest', [CapacityLevelingController::class, 'suggest'])->middleware('check.permission:manufacturing.capacity.view')->name('suggest');
        Route::post('/apply', [CapacityLevelingController::class, 'apply'])->middleware('check.permission:manufacturing.capacity.manage')->name('apply');
        Route::get('/work-orders/{workOrder}/alternative-work-centers', [CapacityLevelingController::class, 'alternativeWorkCenters'])->middleware('check.permission:manufacturing.capacity.view')->name('alternative-work-centers');
    });

    /*
    |--------------------------------------------------------------------------
    | Statistical Process Control (SPC)
    |--------------------------------------------------------------------------
    */
    Route::prefix('spc')->middleware('check.permission:manufacturing.quality.view')->group(function (): void {
        Route::post('/xbar-r', [SpcController::class, 'calculateXbarR'])->name('manufacturing.spc.xbar-r');
        Route::post('/cpk', [SpcController::class, 'calculateCpk'])->name('manufacturing.spc.cpk');
        Route::get('/inspection-lot/{inspectionLot}/chart', [SpcController::class, 'inspectionLotChart'])
            ->name('manufacturing.spc.inspection_lot_chart');

        // Persistent SPC charts
        Route::post('/charts', [SpcController::class, 'createChart'])
            ->middleware('check.permission:manufacturing.quality.create')
            ->name('manufacturing.spc.charts.store');
        Route::post('/charts/{chartId}/subgroups', [SpcController::class, 'recordSubgroup'])
            ->middleware('check.permission:manufacturing.quality.create')
            ->name('manufacturing.spc.subgroups.store');
        Route::get('/charts/{chartId}/trend', [SpcController::class, 'trend'])
            ->name('manufacturing.spc.trend');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-CA: Calibration Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('calibration')->name('manufacturing.calibration.')->group(function (): void {
        Route::get('/equipment', [CalibrationController::class, 'equipment'])->name('equipment');
        Route::post('/equipment', [CalibrationController::class, 'storeEquipment'])->name('equipment.store');
        Route::get('/equipment/{id}', [CalibrationController::class, 'showEquipment'])->name('equipment.show');
        Route::put('/equipment/{id}', [CalibrationController::class, 'updateEquipment'])->name('equipment.update');
        Route::get('/plans', [CalibrationController::class, 'plans'])->name('plans');
        Route::post('/plans', [CalibrationController::class, 'storePlan'])->name('plans.store');
        Route::get('/plans/{id}', [CalibrationController::class, 'showPlan'])->name('plans.show');
        Route::get('/orders', [CalibrationController::class, 'orders'])->name('orders');
        Route::post('/orders', [CalibrationController::class, 'storeOrder'])->name('orders.store');
        Route::get('/orders/{id}', [CalibrationController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{id}/complete', [CalibrationController::class, 'completeOrder'])->name('orders.complete');
        Route::get('/orders/{id}/certificates', [CalibrationController::class, 'certificates'])->name('orders.certificates');
        Route::get('/overdue', [CalibrationController::class, 'overdue'])->name('overdue');
        Route::get('/upcoming', [CalibrationController::class, 'upcoming'])->name('upcoming');
        Route::post('/generate-orders', [CalibrationController::class, 'generateOrders'])->name('generate-orders');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-IM: QM in Procurement (Goods Receipt Inspection)
    |--------------------------------------------------------------------------
    */
    Route::prefix('procurement-inspection')->name('manufacturing.procurement-inspection.')->group(function (): void {
        Route::get('/configs', [ProcurementInspectionController::class, 'configs'])->name('configs');
        Route::post('/configs', [ProcurementInspectionController::class, 'storeConfig'])->name('configs.store');
        Route::put('/configs/{id}', [ProcurementInspectionController::class, 'updateConfig'])->name('configs.update');
        Route::get('/inspections', [ProcurementInspectionController::class, 'inspections'])->name('inspections');
        Route::post('/inspections', [ProcurementInspectionController::class, 'createInspection'])->name('inspections.store');
        Route::get('/inspections/{id}', [ProcurementInspectionController::class, 'showInspection'])->name('inspections.show');
        Route::post('/inspections/{id}/results', [ProcurementInspectionController::class, 'recordResults'])->name('inspections.results');
        Route::post('/inspections/{id}/approve', [ProcurementInspectionController::class, 'approve'])->name('inspections.approve');
        Route::post('/inspections/{id}/reject', [ProcurementInspectionController::class, 'reject'])->name('inspections.reject');
        Route::get('/vendor/{vendorId}/quality-score', [ProcurementInspectionController::class, 'vendorQualityScore'])->name('vendor-quality-score');
    });

    /*
    |--------------------------------------------------------------------------
    | Production Versions (SAP PP - Production Versions)
    |--------------------------------------------------------------------------
    */
    Route::prefix('production-versions')->name('manufacturing.production-versions.')->group(function (): void {
        Route::get('/', [ProductionVersionController::class, 'index'])->name('index');
        Route::post('/', [ProductionVersionController::class, 'store'])->name('store');
        Route::get('/product/{productId}', [ProductionVersionController::class, 'forProduct'])->name('for-product');
        Route::get('/{id}', [ProductionVersionController::class, 'show'])->name('show');
        Route::put('/{id}', [ProductionVersionController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProductionVersionController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/set-default', [ProductionVersionController::class, 'setDefault'])->name('set-default');
    });

    /*
    |--------------------------------------------------------------------------
    | Repetitive Manufacturing (SAP PP-REM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('repetitive-manufacturing')->name('manufacturing.repetitive.')->group(function (): void {
        Route::get('/lines', [RepetitiveManufacturingController::class, 'lines'])->name('lines');
        Route::post('/lines', [RepetitiveManufacturingController::class, 'storeLine'])->name('lines.store');
        Route::get('/schedules', [RepetitiveManufacturingController::class, 'schedules'])->name('schedules');
        Route::post('/schedules', [RepetitiveManufacturingController::class, 'storeSchedule'])->name('schedules.store');
        Route::get('/schedules/{id}', [RepetitiveManufacturingController::class, 'showSchedule'])->name('schedules.show');
        Route::get('/schedules/{id}/progress', [RepetitiveManufacturingController::class, 'progress'])->name('schedules.progress');
        Route::post('/schedule-lines/{lineId}/confirm', [RepetitiveManufacturingController::class, 'confirmLine'])->name('lines.confirm');
        Route::post('/backflush', [RepetitiveManufacturingController::class, 'backflush'])->name('backflush');
    });

    /*
    |--------------------------------------------------------------------------
    | Process Manufacturing / Process Industries (SAP PP-PI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('process')->name('manufacturing.process.')->group(function (): void {
        Route::get('/recipes', [ProcessOrderController::class, 'recipes'])->name('recipes');
        Route::post('/recipes', [ProcessOrderController::class, 'storeRecipe'])->name('recipes.store');
        Route::get('/recipes/{id}', [ProcessOrderController::class, 'showRecipe'])->name('recipes.show');
        Route::get('/orders', [ProcessOrderController::class, 'orders'])->name('orders');
        Route::post('/orders', [ProcessOrderController::class, 'storeOrder'])->name('orders.store');
        Route::get('/orders/{id}', [ProcessOrderController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{id}/release', [ProcessOrderController::class, 'releaseOrder'])->name('orders.release');
        Route::post('/orders/{id}/complete', [ProcessOrderController::class, 'completeOrder'])->name('orders.complete');
        Route::post('/phases/{phaseId}/start', [ProcessOrderController::class, 'startPhase'])->name('phases.start');
        Route::post('/phases/{phaseId}/complete', [ProcessOrderController::class, 'completePhase'])->name('phases.complete');
    });

    /*
    |--------------------------------------------------------------------------
    | Long-Term Planning Simulation (SAP PP-LTP)
    |--------------------------------------------------------------------------
    */
    Route::prefix('long-term-planning')->name('manufacturing.ltp.')->group(function (): void {
        Route::get('/', [LongTermPlanningController::class, 'index'])->name('index');
        Route::post('/', [LongTermPlanningController::class, 'store'])->name('store');
        Route::get('/{id}', [LongTermPlanningController::class, 'show'])->name('show');
        Route::put('/{id}', [LongTermPlanningController::class, 'update'])->name('update');
        Route::delete('/{id}', [LongTermPlanningController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/run', [LongTermPlanningController::class, 'run'])->name('run');
        Route::get('/{id}/capacity', [LongTermPlanningController::class, 'capacity'])->name('capacity');
        Route::get('/{id}/planned-orders', [LongTermPlanningController::class, 'plannedOrders'])->name('planned-orders');
        Route::get('/{id}/compare', [LongTermPlanningController::class, 'compare'])->name('compare');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-RE: Returns Inspection
    |--------------------------------------------------------------------------
    */
    Route::prefix('returns-inspection')->name('qm.returns-inspection.')->group(function (): void {
        Route::get('/', [ReturnsInspectionController::class, 'index'])->middleware('check.permission:manufacturing.quality.view')->name('index');
        Route::post('/', [ReturnsInspectionController::class, 'store'])->middleware('check.permission:manufacturing.quality.manage')->name('store');
        Route::get('/{id}', [ReturnsInspectionController::class, 'show'])->middleware('check.permission:manufacturing.quality.view')->name('show');
        Route::post('/{id}/start-inspection', [ReturnsInspectionController::class, 'startInspection'])->middleware('check.permission:manufacturing.quality.manage')->name('start');
        Route::post('/{id}/defects', [ReturnsInspectionController::class, 'addDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.add');
        Route::put('/{id}/defects/{defectId}', [ReturnsInspectionController::class, 'updateDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.update');
        Route::delete('/{id}/defects/{defectId}', [ReturnsInspectionController::class, 'removeDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.remove');
        Route::post('/{id}/usage-decision', [ReturnsInspectionController::class, 'makeUsageDecision'])->middleware('check.permission:manufacturing.quality.manage')->name('usage-decision');
        Route::post('/{id}/post-stock', [ReturnsInspectionController::class, 'postStock'])->middleware('check.permission:manufacturing.quality.manage')->name('post-stock');
        Route::post('/{id}/cancel', [ReturnsInspectionController::class, 'cancel'])->middleware('check.permission:manufacturing.quality.manage')->name('cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Detailed Scheduling (SAP PP-DS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('detailed-scheduling')->name('manufacturing.scheduling.')->group(function (): void {
        Route::get('/boards', [DetailedSchedulingController::class, 'boards'])->name('boards');
        Route::post('/boards', [DetailedSchedulingController::class, 'storeBoard'])->name('boards.store');
        Route::get('/boards/{id}/data', [DetailedSchedulingController::class, 'boardData'])->name('boards.data');
        Route::get('/operations', [DetailedSchedulingController::class, 'operations'])->name('operations');
        Route::post('/operations', [DetailedSchedulingController::class, 'storeOperation'])->name('operations.store');
        Route::put('/operations/{id}', [DetailedSchedulingController::class, 'updateOperation'])->name('operations.update');
        Route::post('/operations/{id}/reschedule', [DetailedSchedulingController::class, 'reschedule'])->name('operations.reschedule');
        Route::post('/optimize', [DetailedSchedulingController::class, 'optimize'])->name('optimize');
        Route::get('/conflicts', [DetailedSchedulingController::class, 'conflicts'])->name('conflicts');
    });
});

// Product Cost Collectors (CO-PC-OBJ / Repetitive Manufacturing)
Route::middleware(['auth:api'])->prefix('cost-collectors')->name('pp.cost-collectors.')->group(function () {
    Route::get('/', [ProductCostCollectorController::class, 'index'])->name('index');
    Route::get('/{id}', [ProductCostCollectorController::class, 'show'])->name('show');
    Route::post('/{id}/post-cost', [ProductCostCollectorController::class, 'postCost'])->name('post-cost');
    Route::post('/{id}/recalculate', [ProductCostCollectorController::class, 'recalculate'])->name('recalculate');
    Route::post('/{id}/close', [ProductCostCollectorController::class, 'close'])->name('close');
});

// BOM Alternatives
Route::middleware(['auth:api'])->prefix('bom-alternatives')->name('pp.bom-alternatives.')->group(function () {
    Route::get('/{productId}', [BomAlternativeController::class, 'index'])->name('index');
    Route::post('/{productId}', [BomAlternativeController::class, 'store'])->name('store');
    Route::get('/{productId}/{id}', [BomAlternativeController::class, 'show'])->name('show');
    Route::put('/{productId}/{id}', [BomAlternativeController::class, 'update'])->name('update');
    Route::delete('/{productId}/{id}', [BomAlternativeController::class, 'destroy'])->name('destroy');
    Route::post('/{productId}/{id}/set-default', [BomAlternativeController::class, 'setDefault'])->name('set-default');
    Route::post('/determine', [BomAlternativeController::class, 'determine'])->name('determine');
});

// Engineering Change Management
Route::middleware(['auth:api'])->prefix('engineering-changes')->name('pp.ecm.')->group(function () {
    Route::get('/', [EngineeringChangeController::class, 'index'])->name('index');
    Route::post('/', [EngineeringChangeController::class, 'store'])->name('store');
    Route::get('/for-object', [EngineeringChangeController::class, 'getForObject'])->name('for-object');
    Route::get('/{id}', [EngineeringChangeController::class, 'show'])->name('show');
    Route::put('/{id}', [EngineeringChangeController::class, 'update'])->name('update');
    Route::delete('/{id}', [EngineeringChangeController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/submit', [EngineeringChangeController::class, 'submit'])->name('submit');
    Route::post('/{id}/approve', [EngineeringChangeController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject', [EngineeringChangeController::class, 'reject'])->name('reject');
    Route::post('/{id}/implement', [EngineeringChangeController::class, 'implement'])->name('implement');
    Route::post('/{id}/affected-objects', [EngineeringChangeController::class, 'addAffectedObject'])->name('affected-objects.add');
});

// Production Resource Tools
Route::middleware(['auth:api'])->prefix('production-resources')->name('pp.prt.')->group(function () {
    Route::get('/', [ProductionResourceToolController::class, 'index'])->name('index');
    Route::post('/', [ProductionResourceToolController::class, 'store'])->name('store');
    Route::get('/available', [ProductionResourceToolController::class, 'getAvailable'])->name('available');
    Route::get('/for-work-order/{workOrderId}', [ProductionResourceToolController::class, 'getForWorkOrder'])->name('for-work-order');
    Route::get('/{id}', [ProductionResourceToolController::class, 'show'])->name('show');
    Route::put('/{id}', [ProductionResourceToolController::class, 'update'])->name('update');
    Route::delete('/{id}', [ProductionResourceToolController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/assign', [ProductionResourceToolController::class, 'assign'])->name('assign');
    Route::post('/{id}/assignments/{assignmentId}/release', [ProductionResourceToolController::class, 'release'])->name('release');
});

// Co-Products & By-Products
Route::middleware(['auth:api'])->prefix('co-products')->name('pp.co-products.')->group(function () {
    Route::get('/bom/{bomId}', [CoProductController::class, 'indexForBom'])->name('bom-index');
    Route::post('/bom/{bomId}', [CoProductController::class, 'addToBom'])->name('bom-add');
    Route::put('/bom/{bomId}/{id}', [CoProductController::class, 'updateCoProduct'])->name('update');
    Route::delete('/bom/{bomId}/{id}', [CoProductController::class, 'removeFromBom'])->name('remove');
    Route::get('/work-order/{workOrderId}', [CoProductController::class, 'indexForWorkOrder'])->name('wo-index');
    Route::post('/work-order/{workOrderId}/actuals', [CoProductController::class, 'postActuals'])->name('wo-actuals');
    Route::post('/work-order/{workOrderId}/actuals/{actualId}/post-stock', [CoProductController::class, 'postToStock'])->name('wo-post-stock');
});

// Scrap Reporting
Route::middleware(['auth:api'])->prefix('scrap-reports')->name('pp.scrap.')->group(function () {
    Route::get('/', [ScrapReportingController::class, 'index'])->name('index');
    Route::post('/', [ScrapReportingController::class, 'store'])->name('store');
    Route::get('/summary', [ScrapReportingController::class, 'summary'])->name('summary');
    Route::get('/{id}', [ScrapReportingController::class, 'show'])->name('show');
    Route::put('/{id}', [ScrapReportingController::class, 'update'])->name('update');
    Route::delete('/{id}', [ScrapReportingController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/post-gl', [ScrapReportingController::class, 'postToGL'])->name('post-gl');
});

/*
|--------------------------------------------------------------------------
| QM: Skip Lots / Sampling Procedures
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->group(function (): void {
    Route::prefix('skip-lot-plans')->name('qm.skip-lot.')->group(function (): void {
        Route::get('/', [SkipLotController::class, 'index'])->name('index');
        Route::post('/', [SkipLotController::class, 'store'])->name('store');
        Route::get('/{id}', [SkipLotController::class, 'show'])->name('show');
        Route::put('/{id}', [SkipLotController::class, 'update'])->name('update');
        Route::delete('/{id}', [SkipLotController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('skip-lot-decisions')->name('qm.skip-lot-decisions.')->group(function (): void {
        Route::get('/', [SkipLotController::class, 'decisions'])->name('index');
        Route::post('/should-inspect', [SkipLotController::class, 'shouldInspect'])->name('should-inspect');
        Route::post('/{id}/record-result', [SkipLotController::class, 'recordResult'])->name('record-result');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Quality Cost Analysis
    |--------------------------------------------------------------------------
    */
    Route::prefix('quality-costs')->name('qm.quality-costs.')->group(function (): void {
        Route::get('/', [QualityCostController::class, 'index'])->name('index');
        Route::post('/', [QualityCostController::class, 'store'])->name('store');
        Route::get('/summary', [QualityCostController::class, 'summary'])->name('summary');
        Route::get('/trend', [QualityCostController::class, 'trend'])->name('trend');
        Route::get('/{id}', [QualityCostController::class, 'show'])->name('show');
        Route::put('/{id}', [QualityCostController::class, 'update'])->name('update');
        Route::delete('/{id}', [QualityCostController::class, 'destroy'])->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Q-Info Records
    |--------------------------------------------------------------------------
    */
    Route::prefix('q-info-records')->name('qm.q-info.')->group(function (): void {
        Route::get('/', [QInfoRecordController::class, 'index'])->name('index');
        Route::post('/', [QInfoRecordController::class, 'store'])->name('store');
        Route::get('/due-for-inspection', [QInfoRecordController::class, 'dueForInspection'])->name('due');
        Route::get('/{id}', [QInfoRecordController::class, 'show'])->name('show');
        Route::put('/{id}', [QInfoRecordController::class, 'update'])->name('update');
        Route::delete('/{id}', [QInfoRecordController::class, 'destroy'])->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | PM: Maintenance Permits (Work Permits)
    |--------------------------------------------------------------------------
    */
    Route::prefix('maintenance-permits')->name('pm.permits.')->group(function (): void {
        Route::get('/', [MaintenancePermitController::class, 'index'])->name('index');
        Route::post('/', [MaintenancePermitController::class, 'store'])->name('store');
        Route::get('/{id}', [MaintenancePermitController::class, 'show'])->name('show');
        Route::put('/{id}', [MaintenancePermitController::class, 'update'])->name('update');
        Route::post('/{id}/approve', [MaintenancePermitController::class, 'approve'])->name('approve');
        Route::post('/{id}/activate', [MaintenancePermitController::class, 'activate'])->name('activate');
        Route::post('/{id}/suspend', [MaintenancePermitController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/close', [MaintenancePermitController::class, 'close'])->name('close');
        Route::post('/{id}/safety-checks', [MaintenancePermitController::class, 'addSafetyCheck'])->name('checks.add');
        Route::post('/{id}/safety-checks/{checkId}/complete', [MaintenancePermitController::class, 'completeSafetyCheck'])->name('checks.complete');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Stability Studies
    |--------------------------------------------------------------------------
    */
    Route::prefix('stability-studies')->name('qm.stability.')->group(function (): void {
        Route::get('/', [StabilityStudyController::class, 'index'])->name('index');
        Route::post('/', [StabilityStudyController::class, 'store'])->name('store');
        Route::get('/{id}', [StabilityStudyController::class, 'show'])->name('show');
        Route::put('/{id}', [StabilityStudyController::class, 'update'])->name('update');
        Route::get('/{id}/summary', [StabilityStudyController::class, 'summary'])->name('summary');
        Route::post('/{id}/activate', [StabilityStudyController::class, 'activate'])->name('activate');
        Route::post('/{id}/complete', [StabilityStudyController::class, 'complete'])->name('complete');
        Route::post('/{id}/time-points', [StabilityStudyController::class, 'addTimePoint'])->name('timepoints.add');
        Route::put('/{id}/time-points/{tpId}', [StabilityStudyController::class, 'updateTimePoint'])->name('timepoints.update');
        Route::post('/{id}/time-points/{tpId}/results', [StabilityStudyController::class, 'addResult'])->name('results.add');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Audit Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('audit-plans')->name('qm.audits.')->group(function (): void {
        Route::get('/', [AuditManagementController::class, 'index'])->name('index');
        Route::post('/', [AuditManagementController::class, 'store'])->name('store');
        Route::get('/{id}', [AuditManagementController::class, 'show'])->name('show');
        Route::post('/{id}/checklists', [AuditManagementController::class, 'addChecklist'])->name('checklists.add');
        Route::put('/{id}/checklists/{checklistId}', [AuditManagementController::class, 'updateChecklist'])->name('checklists.update');
        Route::post('/{id}/findings', [AuditManagementController::class, 'addFinding'])->name('findings.add');
        Route::post('/{id}/findings/{findingId}/close', [AuditManagementController::class, 'closeFinding'])->name('findings.close');
        Route::post('/{id}/report', [AuditManagementController::class, 'createReport'])->name('report.create');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: CAPA Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('capas')->name('qm.capas.')->group(function (): void {
        Route::get('/', [CapaController::class, 'index'])->name('index');
        Route::post('/', [CapaController::class, 'store'])->name('store');
        Route::get('/{id}', [CapaController::class, 'show'])->name('show');
        Route::post('/{id}/actions', [CapaController::class, 'addAction'])->name('actions.add');
        Route::post('/{id}/actions/{actionId}/complete', [CapaController::class, 'completeAction'])->name('actions.complete');
        Route::post('/{id}/effectiveness-reviews', [CapaController::class, 'addEffectivenessReview'])->name('effectiveness.add');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Complaint Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('complaints')->name('qm.complaints.')->group(function (): void {
        Route::get('/', [ComplaintController::class, 'index'])->name('index');
        Route::post('/', [ComplaintController::class, 'store'])->name('store');
        Route::get('/{id}', [ComplaintController::class, 'show'])->name('show');
        Route::post('/{id}/communications', [ComplaintController::class, 'addCommunication'])->name('comms.add');
        Route::post('/{id}/resolve', [ComplaintController::class, 'resolve'])->name('resolve');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Supplier Quality
    |--------------------------------------------------------------------------
    */
    Route::prefix('supplier-quality')->name('qm.sq.')->group(function (): void {
        Route::get('/ratings', [SupplierQualityController::class, 'ratings'])->name('ratings');
        Route::post('/ratings', [SupplierQualityController::class, 'storeRating'])->name('ratings.store');
        Route::get('/avl', [SupplierQualityController::class, 'avl'])->name('avl');
        Route::post('/avl', [SupplierQualityController::class, 'storeAvl'])->name('avl.store');
        Route::get('/ncrs', [SupplierQualityController::class, 'ncrs'])->name('ncrs');
        Route::post('/ncrs', [SupplierQualityController::class, 'storeNcr'])->name('ncrs.store');
        Route::post('/ncrs/{id}/close', [SupplierQualityController::class, 'closeNcr'])->name('ncrs.close');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Dynamic Modification Rules (SAP QP27)
    |--------------------------------------------------------------------------
    */
    Route::prefix('dynamic-modification-rules')->name('qm.dmr.')->group(function (): void {
        Route::get('/', [DynamicModificationController::class, 'index'])->name('index');
        Route::post('/', [DynamicModificationController::class, 'store'])->name('store');
        Route::get('/{uuid}', [DynamicModificationController::class, 'show'])->name('show');
        Route::get('/{uuid}/stage', [DynamicModificationController::class, 'currentStage'])->name('stage');
        Route::post('/{uuid}/evaluate', [DynamicModificationController::class, 'evaluate'])->name('evaluate');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: 8D CAPA (Eight-Discipline Problem Solving)
    |--------------------------------------------------------------------------
    */
    Route::prefix('capa-8d')->name('qm.capa8d.')->group(function (): void {
        Route::get('/', [Capa8DController::class, 'index'])->name('index');
        Route::post('/', [Capa8DController::class, 'store'])->name('store');
        Route::get('/{uuid}', [Capa8DController::class, 'show'])->name('show');
        Route::post('/{uuid}/steps/{step}', [Capa8DController::class, 'updateStep'])->name('steps.update');
        Route::post('/{uuid}/close', [Capa8DController::class, 'close'])->name('close');
    });
});
