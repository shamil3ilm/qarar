<?php

use App\Http\Controllers\Api\V1\Sales\ProductBundleController;
use App\Http\Controllers\Api\V1\Sales\SeasonalCampaignController;
use Illuminate\Support\Facades\Route;

// Product Bundles
Route::prefix('bundles')->group(function () {
    Route::get('/', [ProductBundleController::class, 'index']);
    Route::post('/', [ProductBundleController::class, 'store']);
    Route::get('/{bundle}', [ProductBundleController::class, 'show']);
    Route::put('/{bundle}', [ProductBundleController::class, 'update']);
    Route::delete('/{bundle}', [ProductBundleController::class, 'destroy']);
    Route::post('/{bundle}/calculate', [ProductBundleController::class, 'calculatePrice']);
});

// Seasonal Campaigns
Route::prefix('campaigns')->group(function () {
    Route::get('/', [SeasonalCampaignController::class, 'index']);
    Route::get('/active', [SeasonalCampaignController::class, 'active']);
    Route::post('/', [SeasonalCampaignController::class, 'store']);
    Route::get('/{campaign}', [SeasonalCampaignController::class, 'show']);
    Route::put('/{campaign}', [SeasonalCampaignController::class, 'update']);
    Route::delete('/{campaign}', [SeasonalCampaignController::class, 'destroy']);
    Route::post('/{campaign}/tier-offers', [SeasonalCampaignController::class, 'addTierOffer']);
});
