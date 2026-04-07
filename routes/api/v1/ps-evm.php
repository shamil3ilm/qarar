<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Projects\EarnedValueController;
use App\Http\Controllers\Api\V1\Projects\ProjectSettlementController;
use App\Http\Controllers\Api\V1\Projects\WbsController;
use Illuminate\Support\Facades\Route;

Route::get('projects/{projectId}/wbs', [WbsController::class, 'hierarchy'])
    ->name('ps.wbs.hierarchy');

Route::post('projects/{projectId}/wbs', [WbsController::class, 'createElement'])
    ->name('ps.wbs.create');

Route::put('projects/{projectId}/wbs/{wbsElement}', [WbsController::class, 'updateElement'])
    ->name('ps.wbs.update');

Route::post('projects/{projectId}/wbs/rollup', [WbsController::class, 'rollupCosts'])
    ->name('ps.wbs.rollup');

Route::post('projects/{projectId}/evm/snapshot', [EarnedValueController::class, 'calculateSnapshot'])
    ->name('ps.evm.snapshot');

Route::get('projects/{projectId}/evm/latest', [EarnedValueController::class, 'latestSnapshot'])
    ->name('ps.evm.latest');

Route::get('projects/{projectId}/evm/history', [EarnedValueController::class, 'history'])
    ->name('ps.evm.history');

Route::post('projects/{projectId}/settlement-rules', [ProjectSettlementController::class, 'defineRule'])
    ->name('ps.settlement.rule');

Route::post('projects/{projectId}/settle', [ProjectSettlementController::class, 'settle'])
    ->name('ps.settlement.settle');
