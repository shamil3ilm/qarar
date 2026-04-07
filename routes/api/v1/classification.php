<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\ClassificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Classification System Routes (Platform)
|--------------------------------------------------------------------------
*/

Route::prefix('classification')->name('core.classification.')->group(function () {
    Route::get('/classes', [ClassificationController::class, 'indexClasses'])->name('classes.index');
    Route::post('/classes', [ClassificationController::class, 'storeClass'])->name('classes.store');
    Route::get('/classes/{id}', [ClassificationController::class, 'showClass'])->name('classes.show');
    Route::put('/classes/{id}', [ClassificationController::class, 'updateClass'])->name('classes.update');
    Route::delete('/classes/{id}', [ClassificationController::class, 'destroyClass'])->name('classes.destroy');
    Route::post('/classes/{classId}/characteristics', [ClassificationController::class, 'addCharacteristic'])->name('chars.add');
    Route::put('/classes/{classId}/characteristics/{charId}', [ClassificationController::class, 'updateCharacteristic'])->name('chars.update');
    Route::post('/assign', [ClassificationController::class, 'assignToObject'])->name('assign');
    Route::post('/values', [ClassificationController::class, 'setValues'])->name('values.set');
    Route::get('/for-object', [ClassificationController::class, 'getForObject'])->name('for-object');
    Route::post('/search', [ClassificationController::class, 'search'])->name('search');
});
