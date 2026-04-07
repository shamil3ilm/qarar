<?php

use App\Http\Controllers\Api\V1\Core\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Activity Logging Routes
|--------------------------------------------------------------------------
|
| Routes for the activity logging module including activity logs,
| user sessions, login history, and entity views tracking.
|
*/

Route::prefix('activity-logs')->group(function () {
    // Activity logs listing with filters
    Route::get('/', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');

    // Activity logs for a specific entity
    Route::get('/entity', [ActivityLogController::class, 'getForEntity'])
        ->name('activity-logs.entity');

    // Activity logs for a specific user
    Route::get('/user/{userId}', [ActivityLogController::class, 'getForUser'])
        ->name('activity-logs.user');

    // Activity statistics
    Route::get('/statistics', [ActivityLogController::class, 'statistics'])
        ->name('activity-logs.statistics');

    // Popular entities
    Route::get('/popular-entities', [ActivityLogController::class, 'popularEntities'])
        ->name('activity-logs.popular-entities');
});

// User sessions
Route::prefix('sessions')->group(function () {
    // Current user's sessions
    Route::get('/', [ActivityLogController::class, 'sessions'])
        ->name('sessions.index');

    // All active sessions (admin)
    Route::get('/all', [ActivityLogController::class, 'allSessions'])
        ->middleware('check.permission:core.users.view')
        ->name('sessions.all');

    // Terminate a session
    Route::post('/{session}/terminate', [ActivityLogController::class, 'terminateSession'])
        ->name('sessions.terminate');
});

// Login history
Route::prefix('login-history')->group(function () {
    Route::get('/', [ActivityLogController::class, 'loginHistory'])
        ->name('login-history.index');
});

// Entity views (recently viewed)
Route::prefix('entity-views')->group(function () {
    // Get recently viewed entities
    Route::get('/recent', [ActivityLogController::class, 'recentlyViewed'])
        ->name('entity-views.recent');

    // Record a view
    Route::post('/', [ActivityLogController::class, 'recordView'])
        ->name('entity-views.record');
});
