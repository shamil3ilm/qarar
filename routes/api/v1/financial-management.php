<?php

use App\Http\Controllers\Api\V1\Accounting\CashFlowController;
use App\Http\Controllers\Api\V1\Accounting\CreditManagementController;
use App\Http\Controllers\Api\V1\Accounting\DunningController;
use App\Http\Controllers\Api\V1\Accounting\PettyCashController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financial Management API Routes
|--------------------------------------------------------------------------
|
| Dunning / Collections (SAP FI-AR)
| Credit Management (SAP FSCM)
| Petty Cash Management (SAP FI Cash Journal)
| Cash Flow Forecasting (SAP TRM)
|
| All routes require: auth:api, validate.jwt, check.organization, check.module:accounting
|
*/

// -------------------------------------------------------------------------
// Dunning / Collections
// -------------------------------------------------------------------------
Route::prefix('dunning')->group(function () {
    // Dunning levels (configuration)
    Route::get('/levels', [DunningController::class, 'indexLevels'])
        ->middleware('check.permission:accounting.dunning.view')
        ->name('accounting.dunning.levels.index');

    Route::post('/levels', [DunningController::class, 'storeLevels'])
        ->middleware('check.permission:accounting.dunning.configure')
        ->name('accounting.dunning.levels.store');

    Route::put('/levels/{dunningLevel}', [DunningController::class, 'updateLevel'])
        ->middleware('check.permission:accounting.dunning.configure')
        ->name('accounting.dunning.levels.update');

    Route::delete('/levels/{dunningLevel}', [DunningController::class, 'destroyLevel'])
        ->middleware('check.permission:accounting.dunning.configure')
        ->name('accounting.dunning.levels.destroy');

    // Dunning runs
    Route::post('/run', [DunningController::class, 'runDunning'])
        ->middleware('check.permission:accounting.dunning.run')
        ->name('accounting.dunning.run');

    Route::get('/runs', [DunningController::class, 'indexRuns'])
        ->middleware('check.permission:accounting.dunning.view')
        ->name('accounting.dunning.runs.index');

    Route::get('/runs/{dunningRun}', [DunningController::class, 'showRun'])
        ->middleware('check.permission:accounting.dunning.view')
        ->name('accounting.dunning.runs.show');

    Route::post('/runs/{dunningRun}/post', [DunningController::class, 'postRun'])
        ->middleware('check.permission:accounting.dunning.run')
        ->name('accounting.dunning.runs.post');

    Route::post('/runs/{dunningRun}/send-notices', [DunningController::class, 'sendNotices'])
        ->middleware('check.permission:accounting.dunning.send')
        ->name('accounting.dunning.runs.send-notices');

    // Dunning blocks
    Route::get('/blocks', [DunningController::class, 'indexBlocks'])
        ->middleware('check.permission:accounting.dunning.view')
        ->name('accounting.dunning.blocks.index');

    Route::post('/blocks/contacts/{contact}', [DunningController::class, 'createBlock'])
        ->middleware('check.permission:accounting.dunning.block')
        ->name('accounting.dunning.blocks.store');

    Route::post('/blocks/{dunningBlock}/release', [DunningController::class, 'releaseBlock'])
        ->middleware('check.permission:accounting.dunning.block')
        ->name('accounting.dunning.blocks.release');
});

// -------------------------------------------------------------------------
// Credit Management
// -------------------------------------------------------------------------
Route::prefix('credit-management')->group(function () {
    // Credit limits
    Route::get('/limits', [CreditManagementController::class, 'indexLimits'])
        ->middleware('check.permission:accounting.credit.view')
        ->name('accounting.credit.limits.index');

    Route::post('/limits', [CreditManagementController::class, 'storeLimit'])
        ->middleware('check.permission:accounting.credit.manage')
        ->name('accounting.credit.limits.store');

    Route::put('/limits/{creditLimit}', [CreditManagementController::class, 'updateLimit'])
        ->middleware('check.permission:accounting.credit.manage')
        ->name('accounting.credit.limits.update');

    // Credit exposure
    Route::get('/exposure/contacts/{contact}', [CreditManagementController::class, 'showExposure'])
        ->middleware('check.permission:accounting.credit.view')
        ->name('accounting.credit.exposure.show');

    Route::get('/exposure/contacts/{contact}/snapshots', [CreditManagementController::class, 'indexExposureSnapshots'])
        ->middleware('check.permission:accounting.credit.view')
        ->name('accounting.credit.exposure.snapshots');

    Route::post('/exposure/snapshot', [CreditManagementController::class, 'snapshotExposures'])
        ->middleware('check.permission:accounting.credit.manage')
        ->name('accounting.credit.exposure.snapshot');

    // Credit holds
    Route::get('/holds', [CreditManagementController::class, 'indexHolds'])
        ->middleware('check.permission:accounting.credit.view')
        ->name('accounting.credit.holds.index');

    Route::post('/holds/contacts/{contact}', [CreditManagementController::class, 'placeHold'])
        ->middleware('check.permission:accounting.credit.hold')
        ->name('accounting.credit.holds.store');

    Route::post('/holds/{creditHold}/release', [CreditManagementController::class, 'releaseHold'])
        ->middleware('check.permission:accounting.credit.hold')
        ->name('accounting.credit.holds.release');
});

// -------------------------------------------------------------------------
// Petty Cash Management
// -------------------------------------------------------------------------
Route::prefix('petty-cash')->group(function () {
    // Funds
    Route::get('/funds', [PettyCashController::class, 'indexFunds'])
        ->middleware('check.permission:accounting.petty-cash.view')
        ->name('accounting.petty-cash.funds.index');

    Route::post('/funds', [PettyCashController::class, 'storeFund'])
        ->middleware('check.permission:accounting.petty-cash.manage')
        ->name('accounting.petty-cash.funds.store');

    Route::get('/funds/{pettyCashFund}', [PettyCashController::class, 'showFund'])
        ->middleware('check.permission:accounting.petty-cash.view')
        ->name('accounting.petty-cash.funds.show');

    Route::put('/funds/{pettyCashFund}', [PettyCashController::class, 'updateFund'])
        ->middleware('check.permission:accounting.petty-cash.manage')
        ->name('accounting.petty-cash.funds.update');

    // Vouchers
    Route::get('/funds/{pettyCashFund}/vouchers', [PettyCashController::class, 'indexVouchers'])
        ->middleware('check.permission:accounting.petty-cash.view')
        ->name('accounting.petty-cash.vouchers.index');

    Route::post('/funds/{pettyCashFund}/vouchers', [PettyCashController::class, 'storeVoucher'])
        ->middleware('check.permission:accounting.petty-cash.create')
        ->name('accounting.petty-cash.vouchers.store');

    Route::post('/vouchers/{pettyCashVoucher}/approve', [PettyCashController::class, 'approveVoucher'])
        ->middleware('check.permission:accounting.petty-cash.approve')
        ->name('accounting.petty-cash.vouchers.approve');

    Route::post('/vouchers/{pettyCashVoucher}/post', [PettyCashController::class, 'postVoucher'])
        ->middleware('check.permission:accounting.petty-cash.post')
        ->name('accounting.petty-cash.vouchers.post');

    // Replenishments
    Route::get('/funds/{pettyCashFund}/replenishments', [PettyCashController::class, 'indexReplenishments'])
        ->middleware('check.permission:accounting.petty-cash.view')
        ->name('accounting.petty-cash.replenishments.index');

    Route::post('/funds/{pettyCashFund}/replenishments', [PettyCashController::class, 'requestReplenishment'])
        ->middleware('check.permission:accounting.petty-cash.create')
        ->name('accounting.petty-cash.replenishments.store');

    Route::post('/replenishments/{pettyCashReplenishment}/approve', [PettyCashController::class, 'approveReplenishment'])
        ->middleware('check.permission:accounting.petty-cash.approve')
        ->name('accounting.petty-cash.replenishments.approve');

    Route::post('/replenishments/{pettyCashReplenishment}/disburse', [PettyCashController::class, 'disburseReplenishment'])
        ->middleware('check.permission:accounting.petty-cash.post')
        ->name('accounting.petty-cash.replenishments.disburse');
});

// -------------------------------------------------------------------------
// Cash Flow Forecasting
// -------------------------------------------------------------------------
Route::prefix('cash-flow')->group(function () {
    // Forecasts
    Route::post('/forecasts/generate', [CashFlowController::class, 'generateForecast'])
        ->middleware('check.permission:accounting.cash-flow.generate')
        ->name('accounting.cash-flow.forecasts.generate');

    Route::get('/forecasts', [CashFlowController::class, 'indexForecasts'])
        ->middleware('check.permission:accounting.cash-flow.view')
        ->name('accounting.cash-flow.forecasts.index');

    Route::get('/forecasts/{cashFlowForecast}', [CashFlowController::class, 'showForecast'])
        ->middleware('check.permission:accounting.cash-flow.view')
        ->name('accounting.cash-flow.forecasts.show');

    Route::post('/forecasts/{cashFlowForecast}/refresh', [CashFlowController::class, 'refreshForecast'])
        ->middleware('check.permission:accounting.cash-flow.generate')
        ->name('accounting.cash-flow.forecasts.refresh');

    Route::get('/forecasts/{cashFlowForecast}/lines', [CashFlowController::class, 'forecastLines'])
        ->middleware('check.permission:accounting.cash-flow.view')
        ->name('accounting.cash-flow.forecasts.lines');

    // Scenarios
    Route::get('/scenarios', [CashFlowController::class, 'indexScenarios'])
        ->middleware('check.permission:accounting.cash-flow.view')
        ->name('accounting.cash-flow.scenarios.index');

    Route::post('/scenarios', [CashFlowController::class, 'storeScenario'])
        ->middleware('check.permission:accounting.cash-flow.manage')
        ->name('accounting.cash-flow.scenarios.store');

    Route::put('/scenarios/{cashFlowScenario}', [CashFlowController::class, 'updateScenario'])
        ->middleware('check.permission:accounting.cash-flow.manage')
        ->name('accounting.cash-flow.scenarios.update');

    Route::delete('/scenarios/{cashFlowScenario}', [CashFlowController::class, 'destroyScenario'])
        ->middleware('check.permission:accounting.cash-flow.manage')
        ->name('accounting.cash-flow.scenarios.destroy');
});
