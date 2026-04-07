<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\WarehouseTransferOrderController;
use Illuminate\Support\Facades\Route;

Route::apiResource('transfer-orders', WarehouseTransferOrderController::class)->names('inventory.transfer-orders');
Route::post('transfer-orders/{warehouseTransferOrder}/start', [WarehouseTransferOrderController::class, 'startTransfer'])->name('inventory.transfer-orders.start');
Route::post('transfer-orders/{warehouseTransferOrder}/confirm', [WarehouseTransferOrderController::class, 'confirmTransfer'])->name('inventory.transfer-orders.confirm');
Route::post('transfer-orders/{warehouseTransferOrder}/cancel', [WarehouseTransferOrderController::class, 'cancel'])->name('inventory.transfer-orders.cancel');
