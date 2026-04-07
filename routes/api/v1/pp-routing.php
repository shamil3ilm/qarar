<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Manufacturing\CapacityLevelingController;
use App\Http\Controllers\Api\V1\Manufacturing\KanbanController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductionSchedulingController;
use App\Http\Controllers\Api\V1\Manufacturing\RoutingController;
use Illuminate\Support\Facades\Route;

Route::apiResource('routings', RoutingController::class)
    ->names('manufacturing.routings');

Route::post('routings/{routingHeader}/operations', [RoutingController::class, 'addOperation'])
    ->name('manufacturing.routings.add-operation');

Route::get('routings/{productId}/lead-time', [RoutingController::class, 'calculateLeadTime'])
    ->name('manufacturing.routings.lead-time');

// ── Gap 3: Production Scheduling ─────────────────────────────────────────────

Route::post(
    'work-orders/{workOrder}/schedule/forward',
    [ProductionSchedulingController::class, 'scheduleForward']
)->name('manufacturing.schedule.forward');

Route::post(
    'work-orders/{workOrder}/schedule/backward',
    [ProductionSchedulingController::class, 'scheduleBackward']
)->name('manufacturing.schedule.backward');

Route::post(
    'schedule/reschedule-all',
    [ProductionSchedulingController::class, 'rescheduleAll']
)->name('manufacturing.schedule.reschedule-all');

Route::get(
    'schedule/gantt',
    [ProductionSchedulingController::class, 'gantt']
)->name('manufacturing.schedule.gantt');

// ── Gap 4: Kanban / Pull-Based Production ─────────────────────────────────────

Route::prefix('kanban')->name('manufacturing.kanban.')->group(function () {
    // Supply areas
    Route::get('supply-areas', [KanbanController::class, 'indexSupplyAreas'])
        ->name('supply-areas.index');
    Route::post('supply-areas', [KanbanController::class, 'storeSupplyArea'])
        ->name('supply-areas.store');
    Route::get('supply-areas/{kanbanSupplyArea}', [KanbanController::class, 'showSupplyArea'])
        ->name('supply-areas.show');
    Route::put('supply-areas/{kanbanSupplyArea}', [KanbanController::class, 'updateSupplyArea'])
        ->name('supply-areas.update');
    Route::delete('supply-areas/{kanbanSupplyArea}', [KanbanController::class, 'destroySupplyArea'])
        ->name('supply-areas.destroy');

    // Control cycles
    Route::get('control-cycles', [KanbanController::class, 'indexControlCycles'])
        ->name('cycles.index');
    Route::post('control-cycles', [KanbanController::class, 'storeControlCycle'])
        ->name('cycles.store');
    Route::get('control-cycles/{kanbanControlCycle}', [KanbanController::class, 'showControlCycle'])
        ->name('cycles.show');
    Route::put('control-cycles/{kanbanControlCycle}', [KanbanController::class, 'updateControlCycle'])
        ->name('cycles.update');
    Route::delete('control-cycles/{kanbanControlCycle}', [KanbanController::class, 'destroyControlCycle'])
        ->name('cycles.destroy');

    // Cards
    Route::get('control-cycles/{kanbanControlCycle}/cards', [KanbanController::class, 'cards'])
        ->name('cards');
    Route::post('cards/{kanbanCard}/empty', [KanbanController::class, 'signalEmpty'])
        ->name('empty');
    Route::post('cards/{kanbanCard}/full', [KanbanController::class, 'signalFull'])
        ->name('full');

    // Board view
    Route::get('board', [KanbanController::class, 'board'])
        ->name('board');
});

// ── Gap 17: Capacity Leveling ─────────────────────────────────────────────────

Route::prefix('capacity-leveling')->name('manufacturing.capacity-leveling.')->group(function () {
    Route::get('suggest', [CapacityLevelingController::class, 'suggest'])
        ->name('suggest');
    Route::post('apply', [CapacityLevelingController::class, 'apply'])
        ->name('apply');
    Route::get(
        'work-orders/{workOrder}/alternative-work-centers',
        [CapacityLevelingController::class, 'alternativeWorkCenters']
    )->name('alternative-wc');
});
