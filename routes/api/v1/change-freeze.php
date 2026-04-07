<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\ChangeFreezeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Change Freeze Period Routes
|--------------------------------------------------------------------------
|
| Prefix: /api/v1/change-freeze (loaded inside the protected middleware group)
|
*/

Route::middleware(['auth:api', 'check.permission:core.change-freeze.manage'])->group(function () {
    Route::get('/', [ChangeFreezeController::class, 'index'])
        ->name('core.change-freeze.index');

    Route::post('/', [ChangeFreezeController::class, 'store'])
        ->name('core.change-freeze.store');

    Route::get('/{id}', [ChangeFreezeController::class, 'show'])
        ->name('core.change-freeze.show');

    Route::post('/{id}/end', [ChangeFreezeController::class, 'endFreeze'])
        ->name('core.change-freeze.end');

    Route::delete('/{id}', [ChangeFreezeController::class, 'destroy'])
        ->name('core.change-freeze.destroy');
});
