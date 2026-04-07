<?php

use App\Http\Controllers\Api\V1\Sales\DeliveryModeController;
use App\Http\Controllers\Api\V1\Sales\PaymentModeController;
use App\Http\Controllers\Api\V1\Sales\ShipmentController;
use Illuminate\Support\Facades\Route;

// Payment Modes
Route::prefix('payment-modes')->group(function () {
    Route::get('/', [PaymentModeController::class, 'index']);
    Route::post('/', [PaymentModeController::class, 'store']);
    Route::get('/{mode}', [PaymentModeController::class, 'show']);
    Route::put('/{mode}', [PaymentModeController::class, 'update']);
    Route::delete('/{mode}', [PaymentModeController::class, 'destroy']);
});

// Delivery Modes
Route::prefix('delivery-modes')->group(function () {
    Route::get('/', [DeliveryModeController::class, 'index']);
    Route::post('/', [DeliveryModeController::class, 'store']);
    Route::get('/{mode}', [DeliveryModeController::class, 'show']);
    Route::put('/{mode}', [DeliveryModeController::class, 'update']);
    Route::delete('/{mode}', [DeliveryModeController::class, 'destroy']);
    Route::post('/calculate-shipping', [DeliveryModeController::class, 'calculateShipping']);
});

// Shipments
Route::apiResource('shipments', ShipmentController::class);
Route::post('shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
Route::get('shipments/{shipment}/tracking', [ShipmentController::class, 'tracking']);
