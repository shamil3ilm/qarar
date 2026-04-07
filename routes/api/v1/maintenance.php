<?php

use App\Http\Controllers\Api\V1\Maintenance\EquipmentHierarchyController;
use App\Http\Controllers\Api\V1\Maintenance\FaultAnalysisController;
use App\Http\Controllers\Api\V1\Maintenance\FleetController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceNotificationController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenancePermitController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceReportController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceSettlementController;
use App\Http\Controllers\Api\V1\Maintenance\PmOrderController;
use App\Http\Controllers\Api\V1\Maintenance\PmTaskListController;
use App\Http\Controllers\Api\V1\Maintenance\ServiceOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plant Maintenance API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/maintenance (applied in api.php)
|
*/

Route::middleware(['auth:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Functional Locations
    |--------------------------------------------------------------------------
    */
    Route::prefix('functional-locations')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'functionalLocationIndex'])
            ->middleware('check.permission:maintenance.functional-locations.view')
            ->name('maintenance.functional-locations.index');

        Route::post('/', [MaintenanceController::class, 'functionalLocationStore'])
            ->middleware('check.permission:maintenance.functional-locations.create')
            ->name('maintenance.functional-locations.store');

        Route::get('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationShow'])
            ->middleware('check.permission:maintenance.functional-locations.view')
            ->name('maintenance.functional-locations.show');

        Route::put('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationUpdate'])
            ->middleware('check.permission:maintenance.functional-locations.edit')
            ->name('maintenance.functional-locations.update');

        Route::delete('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationDestroy'])
            ->middleware('check.permission:maintenance.functional-locations.delete')
            ->name('maintenance.functional-locations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Equipment Categories
    |--------------------------------------------------------------------------
    */
    Route::prefix('equipment-categories')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'categoryIndex'])
            ->middleware('check.permission:maintenance.equipment-categories.view')
            ->name('maintenance.equipment-categories.index');

        Route::post('/', [MaintenanceController::class, 'categoryStore'])
            ->middleware('check.permission:maintenance.equipment-categories.create')
            ->name('maintenance.equipment-categories.store');

        Route::get('/{equipmentCategory}', [MaintenanceController::class, 'categoryShow'])
            ->middleware('check.permission:maintenance.equipment-categories.view')
            ->name('maintenance.equipment-categories.show');

        Route::put('/{equipmentCategory}', [MaintenanceController::class, 'categoryUpdate'])
            ->middleware('check.permission:maintenance.equipment-categories.edit')
            ->name('maintenance.equipment-categories.update');

        Route::delete('/{equipmentCategory}', [MaintenanceController::class, 'categoryDestroy'])
            ->middleware('check.permission:maintenance.equipment-categories.delete')
            ->name('maintenance.equipment-categories.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Equipment
    |--------------------------------------------------------------------------
    */
    Route::prefix('equipment')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'equipmentIndex'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.index');

        Route::post('/', [MaintenanceController::class, 'equipmentStore'])
            ->middleware('check.permission:maintenance.equipment.create')
            ->name('maintenance.equipment.store');

        Route::get('/due-soon', [MaintenanceController::class, 'equipmentDueSoon'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.due-soon');

        Route::get('/{equipment}', [MaintenanceController::class, 'equipmentShow'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.show');

        Route::put('/{equipment}', [MaintenanceController::class, 'equipmentUpdate'])
            ->middleware('check.permission:maintenance.equipment.edit')
            ->name('maintenance.equipment.update');

        Route::delete('/{equipment}', [MaintenanceController::class, 'equipmentDestroy'])
            ->middleware('check.permission:maintenance.equipment.delete')
            ->name('maintenance.equipment.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Plans
    |--------------------------------------------------------------------------
    */
    Route::prefix('plans')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'planIndex'])
            ->middleware('check.permission:maintenance.plans.view')
            ->name('maintenance.plans.index');

        Route::post('/', [MaintenanceController::class, 'planStore'])
            ->middleware('check.permission:maintenance.plans.create')
            ->name('maintenance.plans.store');

        Route::put('/{maintenancePlan}', [MaintenanceController::class, 'planUpdate'])
            ->middleware('check.permission:maintenance.plans.edit')
            ->name('maintenance.plans.update');

        Route::post('/{maintenancePlan}/toggle-active', [MaintenanceController::class, 'planToggleActive'])
            ->middleware('check.permission:maintenance.plans.edit')
            ->name('maintenance.plans.toggle-active');

        Route::post('/{maintenancePlan}/generate-order', [MaintenanceController::class, 'planGenerateOrder'])
            ->middleware('check.permission:maintenance.orders.create')
            ->name('maintenance.plans.generate-order');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'orderIndex'])
            ->middleware('check.permission:maintenance.orders.view')
            ->name('maintenance.orders.index');

        Route::post('/', [MaintenanceController::class, 'orderStore'])
            ->middleware('check.permission:maintenance.orders.create')
            ->name('maintenance.orders.store');

        Route::get('/{maintenanceOrder}', [MaintenanceController::class, 'orderShow'])
            ->middleware('check.permission:maintenance.orders.view')
            ->name('maintenance.orders.show');

        Route::put('/{maintenanceOrder}', [MaintenanceController::class, 'orderUpdate'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.update');

        Route::delete('/{maintenanceOrder}', [MaintenanceController::class, 'orderDestroy'])
            ->middleware('check.permission:maintenance.orders.delete')
            ->name('maintenance.orders.destroy');

        Route::post('/{maintenanceOrder}/start', [MaintenanceController::class, 'orderStart'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.start');

        Route::post('/{maintenanceOrder}/tasks/{taskId}/complete', [MaintenanceController::class, 'orderCompleteTask'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.complete-task');

        Route::post('/{maintenanceOrder}/complete', [MaintenanceController::class, 'orderComplete'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.complete');

        Route::post('/{maintenanceOrder}/cancel', [MaintenanceController::class, 'orderCancel'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */
    Route::get('/stats', [MaintenanceController::class, 'stats'])
        ->middleware('check.permission:maintenance.stats.view')
        ->name('maintenance.stats');

    /*
    |--------------------------------------------------------------------------
    | Fleet Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('fleet')->name('maintenance.fleet.')->group(function (): void {
        Route::get('/', [FleetController::class, 'index'])->name('index');
        Route::post('/', [FleetController::class, 'store'])->name('store');
        Route::get('/requiring-service', [FleetController::class, 'requiringService'])->name('requiring-service');
        Route::get('/cost-summary', [FleetController::class, 'costSummary'])->name('cost-summary');
        Route::get('/{id}', [FleetController::class, 'show'])->name('show');
        Route::put('/{id}', [FleetController::class, 'update'])->name('update');
        Route::delete('/{id}', [FleetController::class, 'destroy'])->name('destroy');
        Route::post('/{vehicleId}/assign', [FleetController::class, 'assign'])->name('assign');
        Route::post('/{vehicleId}/unassign', [FleetController::class, 'unassign'])->name('unassign');
        Route::get('/{vehicleId}/mileage-logs', [FleetController::class, 'mileageLogs'])->name('mileage-logs');
        Route::post('/{vehicleId}/mileage-logs', [FleetController::class, 'logMileage'])->name('mileage-logs.store');
        Route::get('/{vehicleId}/fuel-logs', [FleetController::class, 'fuelLogs'])->name('fuel-logs');
        Route::post('/{vehicleId}/fuel-logs', [FleetController::class, 'logFuel'])->name('fuel-logs.store');
        Route::get('/{vehicleId}/maintenance', [FleetController::class, 'maintenanceRecords'])->name('maintenance');
        Route::post('/{vehicleId}/maintenance', [FleetController::class, 'recordMaintenance'])->name('maintenance.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Order Cost Settlement (PM-WOC-CO)
    |--------------------------------------------------------------------------
    */
    Route::get('/maintenance-orders/unsettled', [MaintenanceSettlementController::class, 'unsettledOrders'])
        ->name('maintenance.unsettled');

    Route::prefix('maintenance-orders/{orderId}/costs')->name('maintenance.costs.')->group(function (): void {
        Route::get('/', [MaintenanceSettlementController::class, 'costLines'])->name('index');
        Route::post('/', [MaintenanceSettlementController::class, 'addCostLine'])->name('store');
        Route::get('/total', [MaintenanceSettlementController::class, 'totalCost'])->name('total');
        Route::post('/settle', [MaintenanceSettlementController::class, 'settle'])->name('settle');
        Route::get('/settlement-history', [MaintenanceSettlementController::class, 'settlementHistory'])->name('history');
    });

    /*
    |--------------------------------------------------------------------------
    | Counter-Based PM Scheduling (PM-PRM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('pm-counters')->name('maintenance.pm-counters.')->group(function (): void {
        Route::get('/', [PmOrderController::class, 'counters'])->name('index');
        Route::post('/', [PmOrderController::class, 'storeCounter'])->name('store');
        Route::post('/{counterId}/readings', [PmOrderController::class, 'recordReading'])->name('readings.store');
    });

    Route::prefix('pm-plans')->name('maintenance.pm-plans.')->group(function (): void {
        Route::get('/', [PmOrderController::class, 'plans'])->name('index');
        Route::post('/', [PmOrderController::class, 'storePlan'])->name('store');
        Route::get('/due', [PmOrderController::class, 'dueOrders'])->name('due');
        Route::post('/{planId}/generate-order', [PmOrderController::class, 'generateOrder'])->name('generate-order');
    });

    Route::prefix('pm-orders')->name('maintenance.pm-orders.')->group(function (): void {
        Route::get('/', [PmOrderController::class, 'orders'])->name('index');
        Route::post('/{orderId}/complete', [PmOrderController::class, 'completeOrder'])->name('complete');
    });

    // Work Permits & Safety Checks (PM-WOC-PTW)
    Route::prefix('permits')->name('maintenance.permits.')->group(function (): void {
        Route::get('/', [MaintenancePermitController::class, 'index'])->name('index');
        Route::post('/', [MaintenancePermitController::class, 'store'])->name('store');
        Route::get('/{id}', [MaintenancePermitController::class, 'show'])->name('show');
        Route::put('/{id}', [MaintenancePermitController::class, 'update'])->name('update');
        Route::post('/{id}/approve', [MaintenancePermitController::class, 'approve'])->name('approve');
        Route::post('/{id}/activate', [MaintenancePermitController::class, 'activate'])->name('activate');
        Route::post('/{id}/suspend', [MaintenancePermitController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/close', [MaintenancePermitController::class, 'close'])->name('close');
        Route::post('/{id}/safety-checks', [MaintenancePermitController::class, 'addSafetyCheck'])->name('safety-checks.store');
        Route::post('/{id}/safety-checks/{checkId}/complete', [MaintenancePermitController::class, 'completeSafetyCheck'])->name('safety-checks.complete');
    });

    /*
    |--------------------------------------------------------------------------
    | PM Task Lists (PM-PRM-TL)
    |--------------------------------------------------------------------------
    */
    Route::prefix('pm-task-lists')->name('maintenance.pm-task-lists.')->group(function (): void {
        Route::get('/', [PmTaskListController::class, 'index'])->name('index');
        Route::post('/', [PmTaskListController::class, 'store'])->name('store');
        Route::get('/{pmTaskList}', [PmTaskListController::class, 'show'])->name('show');
        Route::put('/{pmTaskList}', [PmTaskListController::class, 'update'])->name('update');
        Route::delete('/{pmTaskList}', [PmTaskListController::class, 'destroy'])->name('destroy');
        Route::post('/{pmTaskList}/operations', [PmTaskListController::class, 'storeOperation'])->name('operations.store');
        Route::delete('/{pmTaskList}/operations/{operation}', [PmTaskListController::class, 'destroyOperation'])->name('operations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | PMIS Reporting — MTBF / MTTR / OEE / Downtime (PM-IS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('maintenance/reports')->name('maintenance.reports.')->group(function (): void {
        Route::get('kpis', [MaintenanceReportController::class, 'kpiDashboard'])->name('kpis');
        Route::post('kpis/compute', [MaintenanceReportController::class, 'computeKpis'])->name('kpis.compute');
        Route::get('cost-analysis', [MaintenanceReportController::class, 'costAnalysis'])->name('cost-analysis');
    });

    /*
    |--------------------------------------------------------------------------
    | Service Orders — external vendor maintenance (PM-WOC-EXT)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('service-orders', ServiceOrderController::class)
        ->names('maintenance.service-orders');

    /*
    |--------------------------------------------------------------------------
    | Fault Codes & Root Cause Analysis (PM-QM)
    |--------------------------------------------------------------------------
    */
    Route::get('fault-codes', [FaultAnalysisController::class, 'indexFaultCodes'])->name('maintenance.fault-codes.index');
    Route::post('fault-codes', [FaultAnalysisController::class, 'storeFaultCode'])->name('maintenance.fault-codes.store');

    Route::get('rca', [FaultAnalysisController::class, 'indexRca'])->name('maintenance.rca.index');
    Route::post('rca', [FaultAnalysisController::class, 'storeRca'])->name('maintenance.rca.store');
    Route::put('rca/{rca}', [FaultAnalysisController::class, 'updateRca'])->name('maintenance.rca.update');

    /*
    |--------------------------------------------------------------------------
    | Maintenance Notifications (SAP IW21-IW28)
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->name('maintenance.notifications.')->group(function (): void {
        Route::get('/', [MaintenanceNotificationController::class, 'index'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('index');

        Route::post('/', [MaintenanceNotificationController::class, 'store'])
            ->middleware('check.permission:maintenance.notifications.create')
            ->name('store');

        Route::get('/equipment/{equipmentId}', [MaintenanceNotificationController::class, 'byEquipment'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('by-equipment');

        Route::get('/{uuid}', [MaintenanceNotificationController::class, 'show'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('show');

        Route::put('/{uuid}', [MaintenanceNotificationController::class, 'update'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('update');

        Route::post('/{uuid}/complete', [MaintenanceNotificationController::class, 'complete'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('complete');

        Route::post('/{uuid}/tasks', [MaintenanceNotificationController::class, 'addTask'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('tasks.store');
    });
});

/*
|--------------------------------------------------------------------------
| Equipment Hierarchy — SAP PM IL01/IE01
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('equipment-hierarchy')->name('maintenance.equipment-hierarchy.')->group(function (): void {
    Route::get('/tree', [EquipmentHierarchyController::class, 'tree'])->name('tree');
    Route::get('/utilisation-summary', [EquipmentHierarchyController::class, 'utilisationSummary'])->name('utilisation-summary');
    Route::post('/install', [EquipmentHierarchyController::class, 'install'])->name('install');
    Route::post('/deinstall', [EquipmentHierarchyController::class, 'deinstall'])->name('deinstall');
    Route::post('/relocate', [EquipmentHierarchyController::class, 'relocate'])->name('relocate');
    Route::get('/floc/{functionalLocation}/equipment', [EquipmentHierarchyController::class, 'underFloc'])->name('under-floc');
    Route::get('/where-used/{equipment}', [EquipmentHierarchyController::class, 'whereUsed'])->name('where-used');
});
