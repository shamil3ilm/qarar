<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Campaign\CampaignController;
use App\Http\Controllers\Api\V1\Campaign\SegmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('segments')->group(function () {
    Route::get('/', [SegmentController::class, 'index']);
    Route::post('/', [SegmentController::class, 'store']);
    Route::get('/{id}', [SegmentController::class, 'show']);
    Route::put('/{id}', [SegmentController::class, 'update']);
    Route::delete('/{id}', [SegmentController::class, 'destroy']);
    Route::get('/{id}/members', [SegmentController::class, 'members']);
});

Route::prefix('campaigns')->group(function () {
    Route::get('/', [CampaignController::class, 'index']);
    Route::post('/', [CampaignController::class, 'store']);
    Route::get('/{id}', [CampaignController::class, 'show']);
    Route::put('/{id}', [CampaignController::class, 'update']);
    Route::delete('/{id}', [CampaignController::class, 'destroy']);
    Route::post('/{id}/activate', [CampaignController::class, 'activate']);
    Route::post('/{id}/pause', [CampaignController::class, 'pause']);
});
