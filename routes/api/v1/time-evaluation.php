<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\TimeEvaluationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('time-sheets', TimeEvaluationController::class)
    ->names('hr.time-sheets');

Route::post('time-sheets/{timeSheet}/entries', [TimeEvaluationController::class, 'addEntry'])
    ->name('hr.time-sheets.add-entry');

Route::post('time-sheets/{timeSheet}/submit', [TimeEvaluationController::class, 'submit'])
    ->name('hr.time-sheets.submit');

Route::post('time-sheets/{timeSheet}/approve', [TimeEvaluationController::class, 'approve'])
    ->name('hr.time-sheets.approve');

Route::post('time-sheets/{timeSheet}/reject', [TimeEvaluationController::class, 'reject'])
    ->name('hr.time-sheets.reject');

Route::post('time-sheets/{timeSheet}/evaluate', [TimeEvaluationController::class, 'evaluate'])
    ->name('hr.time-sheets.evaluate');

Route::post('time-sheets/{timeSheet}/transfer-payroll', [TimeEvaluationController::class, 'transferToPayroll'])
    ->name('hr.time-sheets.transfer-payroll');

Route::get('time-sheets/{timeSheet}/cost-allocation', [TimeEvaluationController::class, 'costAllocation'])
    ->name('hr.time-sheets.cost-allocation');

Route::prefix('wage-types')->group(function (): void {
    Route::get('/', [TimeEvaluationController::class, 'wageTypes'])
        ->name('hr.wage-types.index');

    Route::post('/', [TimeEvaluationController::class, 'storeWageType'])
        ->name('hr.wage-types.store');

    Route::put('/{timeWageType}', [TimeEvaluationController::class, 'updateWageType'])
        ->name('hr.wage-types.update');

    Route::delete('/{timeWageType}', [TimeEvaluationController::class, 'destroyWageType'])
        ->name('hr.wage-types.destroy');
});
