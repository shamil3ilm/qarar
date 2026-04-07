<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Maintenance\ConditionMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::post('measurements', [ConditionMaintenanceController::class, 'recordMeasurement'])->name('maintenance.measurements.record');
Route::apiResource('condition-rules', ConditionMaintenanceController::class)->names('maintenance.condition-rules');
Route::prefix('equipment/{equipmentId}')->group(function () {
    Route::get('spare-parts', [ConditionMaintenanceController::class, 'spareParts'])->name('maintenance.spare-parts.index');
    Route::post('spare-parts', [ConditionMaintenanceController::class, 'addSparePart'])->name('maintenance.spare-parts.add');
    Route::get('spare-parts/availability', [ConditionMaintenanceController::class, 'sparePartsAvailability'])->name('maintenance.spare-parts.availability');
});
