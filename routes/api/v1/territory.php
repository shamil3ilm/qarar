<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CRM\TerritoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Territory Management Routes
| Prefix: crm/territories  (applied in routes/api.php)
|--------------------------------------------------------------------------
*/

// Territories — static paths must come before wildcard /{id}
Route::middleware('check.permission:crm.territories.view')->group(function () {
    Route::get('/', [TerritoryController::class, 'index']);
    Route::get('/routing-rules', [TerritoryController::class, 'routingRuleIndex']);
    Route::get('/team-workload', [TerritoryController::class, 'teamWorkload']);
    Route::get('/{id}', [TerritoryController::class, 'show']);
    Route::get('/{id}/assignments', [TerritoryController::class, 'assignmentIndex']);
    Route::get('/{id}/performance', [TerritoryController::class, 'performance']);
});

Route::middleware('check.permission:crm.territories.manage')->group(function () {
    Route::post('/', [TerritoryController::class, 'store']);
    Route::put('/{id}', [TerritoryController::class, 'update']);
    Route::delete('/{id}', [TerritoryController::class, 'destroy']);
});

// Territory Assignments
Route::middleware('check.permission:crm.territories.manage')->group(function () {
    Route::post('/{id}/assignments', [TerritoryController::class, 'assignmentStore']);
    Route::delete('/assignments/{id}', [TerritoryController::class, 'assignmentDestroy']);
});

// Routing Rules
Route::middleware('check.permission:crm.territories.manage')->group(function () {
    Route::post('/routing-rules', [TerritoryController::class, 'routingRuleStore']);
    Route::put('/routing-rules/{id}', [TerritoryController::class, 'routingRuleUpdate']);
    Route::delete('/routing-rules/{id}', [TerritoryController::class, 'routingRuleDestroy']);
});

// Actions
Route::middleware('check.permission:crm.territories.manage')->group(function () {
    Route::post('/leads/{id}/auto-assign', [TerritoryController::class, 'autoAssignLead']);
});
