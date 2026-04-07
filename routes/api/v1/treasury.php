<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::prefix('treasury')->group(function (): void {

    // -------------------------------------------------------------------------
    // Treasury Investments
    // -------------------------------------------------------------------------
    Route::get('investments', [TreasuryController::class, 'index'])
        ->name('accounting.treasury.investments.index');
    Route::post('investments', [TreasuryController::class, 'store'])
        ->name('accounting.treasury.investments.store');
    Route::get('investments/{treasuryInvestment}', [TreasuryController::class, 'show'])
        ->name('accounting.treasury.investments.show');
    Route::post('investments/{treasuryInvestment}/accrue', [TreasuryController::class, 'accrueInterest'])
        ->name('accounting.treasury.accrue');
    Route::post('investments/{treasuryInvestment}/mature', [TreasuryController::class, 'mature'])
        ->name('accounting.treasury.mature');
    Route::post('investments/{treasuryInvestment}/pre-liquidate', [TreasuryController::class, 'preLiquidate'])
        ->name('accounting.treasury.pre-liquidate');

    // -------------------------------------------------------------------------
    // Bank Positions
    // -------------------------------------------------------------------------
    Route::get('bank-positions', [TreasuryController::class, 'bankPositions'])
        ->name('accounting.treasury.bank-positions');

    // -------------------------------------------------------------------------
    // Liquidity Plans
    // -------------------------------------------------------------------------
    Route::get('liquidity-plans', [TreasuryController::class, 'liquidityPlans'])
        ->name('accounting.treasury.liquidity-plans.index');
    Route::post('liquidity-plans', [TreasuryController::class, 'createLiquidityPlan'])
        ->name('accounting.treasury.liquidity-plans.store');
    Route::get('liquidity-plans/{liquidityPlan}', [TreasuryController::class, 'showLiquidityPlan'])
        ->name('accounting.treasury.liquidity-plans.show');
    Route::post('liquidity-plans/{liquidityPlan}/update-actuals', [TreasuryController::class, 'updateActuals'])
        ->name('accounting.treasury.update-actuals');

    // -------------------------------------------------------------------------
    // Summaries
    // -------------------------------------------------------------------------
    Route::get('position-summary', [TreasuryController::class, 'positionSummary'])
        ->name('accounting.treasury.position-summary');
    Route::get('maturing-investments', [TreasuryController::class, 'maturingInvestments'])
        ->name('accounting.treasury.maturing');
});
