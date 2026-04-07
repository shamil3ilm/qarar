<?php

use App\Http\Controllers\Api\V1\Core\CustomFieldController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Custom Fields Routes
|--------------------------------------------------------------------------
|
| Routes for the custom fields module including definitions, groups,
| and entity field values management.
|
*/

Route::prefix('custom-fields')->group(function () {
    // Field Definitions listing
    Route::get('/', [CustomFieldController::class, 'index'])
        ->name('custom-fields.index');

    Route::post('/', [CustomFieldController::class, 'store'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-fields.store');

    // Entity-related routes (must be before wildcard {customFieldDefinition})
    Route::get('/entity/grouped', [CustomFieldController::class, 'getGroupedFields'])
        ->name('custom-fields.grouped');

    Route::get('/entity/values', [CustomFieldController::class, 'getEntityFields'])
        ->name('custom-fields.entity.values');

    Route::post('/entity/values', [CustomFieldController::class, 'setEntityFields'])
        ->name('custom-fields.entity.values.set');

    // Field Definition CRUD (wildcard routes last)
    Route::get('/{customFieldDefinition}', [CustomFieldController::class, 'show'])
        ->name('custom-fields.show');

    Route::put('/{customFieldDefinition}', [CustomFieldController::class, 'update'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-fields.update');

    Route::delete('/{customFieldDefinition}', [CustomFieldController::class, 'destroy'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-fields.destroy');
});

// Custom Field Groups
Route::prefix('custom-field-groups')->group(function () {
    Route::get('/', [CustomFieldController::class, 'groups'])
        ->name('custom-field-groups.index');

    Route::post('/', [CustomFieldController::class, 'storeGroup'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-field-groups.store');

    Route::put('/{customFieldGroup}', [CustomFieldController::class, 'updateGroup'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-field-groups.update');

    Route::delete('/{customFieldGroup}', [CustomFieldController::class, 'destroyGroup'])
        ->middleware('check.permission:core.settings.edit')
        ->name('custom-field-groups.destroy');
});
