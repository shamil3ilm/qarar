<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\PhysicalInventoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('physical-inventory', PhysicalInventoryController::class)
    ->names('inventory.physical-inventory');

Route::post('physical-inventory/{physicalInventoryDocument}/counts', [PhysicalInventoryController::class, 'enterCounts'])
    ->name('inventory.physical-inventory.enter-counts');

Route::post('physical-inventory/{physicalInventoryDocument}/post', [PhysicalInventoryController::class, 'postAdjustments'])
    ->name('inventory.physical-inventory.post-adjustments');
