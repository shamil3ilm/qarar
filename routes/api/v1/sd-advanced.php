<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\AtpController;
use App\Http\Controllers\Api\V1\Sales\CustomerMaterialInfoController;
use App\Http\Controllers\Api\V1\Sales\DeliverySplitController;
use App\Http\Controllers\Api\V1\Sales\OutputDeterminationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SD Advanced Routes — ATP, Customer Material Info, Output Determination, Delivery Split
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/sales (via api.php group)
|
*/

// ATP — Available-to-Promise
Route::prefix('atp')->group(function () {
    Route::post('check', [AtpController::class, 'check'])
        ->middleware('check.permission:sales.atp.check')
        ->name('sales.atp.check');

    Route::post('check-order', [AtpController::class, 'checkOrder'])
        ->middleware('check.permission:sales.atp.check')
        ->name('sales.atp.check-order');
});

// Customer Material Information (cross-reference)
Route::get('customer-material-infos/lookup', [CustomerMaterialInfoController::class, 'lookup'])
    ->middleware('check.permission:sales.customer-material-infos.view')
    ->name('sales.customer-material-infos.lookup');

Route::apiResource('customer-material-infos', CustomerMaterialInfoController::class)
    ->middleware([
        'index'   => 'check.permission:sales.customer-material-infos.view',
        'show'    => 'check.permission:sales.customer-material-infos.view',
        'store'   => 'check.permission:sales.customer-material-infos.create',
        'update'  => 'check.permission:sales.customer-material-infos.edit',
        'destroy' => 'check.permission:sales.customer-material-infos.delete',
    ])
    ->names('sales.customer-material-infos');

// Output Determination (SD output control)
Route::prefix('output-determination')->group(function () {
    Route::apiResource('output-types', OutputDeterminationController::class)
        ->middleware([
            'index'   => 'check.permission:sales.output-types.view',
            'show'    => 'check.permission:sales.output-types.view',
            'store'   => 'check.permission:sales.output-types.create',
            'update'  => 'check.permission:sales.output-types.edit',
            'destroy' => 'check.permission:sales.output-types.delete',
        ])
        ->names('sales.output-types');

    Route::get('messages', [OutputDeterminationController::class, 'messages'])
        ->middleware('check.permission:sales.output-types.view')
        ->name('sales.output-messages.index');

    Route::post('messages/{outputMessage}/retry', [OutputDeterminationController::class, 'retryMessage'])
        ->middleware('check.permission:sales.output-types.edit')
        ->name('sales.output-messages.retry');
});

// Delivery Split Rules
Route::post('delivery-split-rules/apply', [DeliverySplitController::class, 'apply'])
    ->middleware('check.permission:sales.delivery-split-rules.view')
    ->name('sales.delivery-split-rules.apply');

Route::apiResource('delivery-split-rules', DeliverySplitController::class)
    ->middleware([
        'index'   => 'check.permission:sales.delivery-split-rules.view',
        'show'    => 'check.permission:sales.delivery-split-rules.view',
        'store'   => 'check.permission:sales.delivery-split-rules.create',
        'update'  => 'check.permission:sales.delivery-split-rules.edit',
        'destroy' => 'check.permission:sales.delivery-split-rules.delete',
    ])
    ->names('sales.delivery-split-rules');
