<?php

use App\Http\Controllers\Api\V1\Loyalty\LoyaltyAccountController;
use App\Http\Controllers\Api\V1\Loyalty\LoyaltyProgramController;
use App\Http\Controllers\Api\V1\Loyalty\RewardsCatalogController;
use Illuminate\Support\Facades\Route;

// Loyalty Programs
Route::prefix('programs')->group(function () {
    Route::get('/', [LoyaltyProgramController::class, 'index']);
    Route::post('/', [LoyaltyProgramController::class, 'store'])->middleware('check.permission:loyalty.programs.create');
    Route::get('/{program}', [LoyaltyProgramController::class, 'show']);
    Route::put('/{program}', [LoyaltyProgramController::class, 'update']);
    Route::delete('/{program}', [LoyaltyProgramController::class, 'destroy'])->middleware('check.permission:loyalty.programs.delete');
    Route::get('/{program}/tiers', [LoyaltyProgramController::class, 'tiers']);
    Route::post('/{program}/tiers', [LoyaltyProgramController::class, 'storeTier']);
    Route::get('/{program}/earning-rules', [LoyaltyProgramController::class, 'earningRules']);
    Route::post('/{program}/earning-rules', [LoyaltyProgramController::class, 'storeEarningRule']);
});

// Loyalty Accounts
Route::prefix('accounts')->group(function () {
    Route::get('/', [LoyaltyAccountController::class, 'index'])->middleware('check.permission:loyalty.accounts.view');
    Route::post('/enroll', [LoyaltyAccountController::class, 'enroll'])->middleware('check.permission:loyalty.accounts.create');
    Route::get('/{account}', [LoyaltyAccountController::class, 'show']);
    Route::get('/{account}/transactions', [LoyaltyAccountController::class, 'transactions']);
    Route::post('/{account}/earn', [LoyaltyAccountController::class, 'earnPoints']);
    Route::post('/{account}/redeem', [LoyaltyAccountController::class, 'redeemReward']);
    Route::get('/{account}/available-rewards', [LoyaltyAccountController::class, 'availableRewards']);
});

// Rewards Catalog
Route::prefix('rewards')->group(function () {
    Route::get('/', [RewardsCatalogController::class, 'index']);
    Route::post('/', [RewardsCatalogController::class, 'store'])->middleware('check.permission:loyalty.rewards.create');
    Route::get('/{reward}', [RewardsCatalogController::class, 'show']);
    Route::put('/{reward}', [RewardsCatalogController::class, 'update']);
    Route::delete('/{reward}', [RewardsCatalogController::class, 'destroy']);
});
