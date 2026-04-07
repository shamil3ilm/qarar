<?php

use App\Http\Controllers\Api\V1\TaskBoard\BoardTaskController;
use App\Http\Controllers\Api\V1\TaskBoard\SprintController;
use App\Http\Controllers\Api\V1\TaskBoard\TaskBoardController;
use Illuminate\Support\Facades\Route;

    // Boards
    Route::apiResource('boards', TaskBoardController::class)->except(['store']);
    Route::post('boards', [TaskBoardController::class, 'store'])->middleware('check.permission:taskboard.boards.create')->name('boards.store');
    Route::post('boards/{board}/members', [TaskBoardController::class, 'addMember']);
    Route::delete('boards/{board}/members/{member}', [TaskBoardController::class, 'removeMember']);
    Route::post('boards/{board}/columns', [TaskBoardController::class, 'addColumn']);
    Route::put('boards/{board}/columns/{column}', [TaskBoardController::class, 'updateColumn']);
    Route::delete('boards/{board}/columns/{column}', [TaskBoardController::class, 'removeColumn']);
    Route::get('boards/{board}/labels', [TaskBoardController::class, 'labels']);
    Route::post('boards/{board}/labels', [TaskBoardController::class, 'addLabel']);

    // Tasks
    Route::prefix('boards/{board}/tasks')->group(function () {
        Route::get('/', [BoardTaskController::class, 'index']);
        Route::post('/', [BoardTaskController::class, 'store'])->middleware('check.permission:taskboard.tasks.create');
        Route::get('/{task}', [BoardTaskController::class, 'show']);
        Route::put('/{task}', [BoardTaskController::class, 'update']);
        Route::delete('/{task}', [BoardTaskController::class, 'destroy']);
        Route::post('/{task}/move', [BoardTaskController::class, 'move']);
        Route::post('/{task}/assign', [BoardTaskController::class, 'assign']);
        Route::post('/{task}/comments', [BoardTaskController::class, 'addComment']);
        Route::post('/{task}/checklists', [BoardTaskController::class, 'addChecklist']);
        Route::post('/{task}/time-entries', [BoardTaskController::class, 'addTimeEntry']);
        Route::post('/{task}/attachments', [BoardTaskController::class, 'addAttachment']);
        Route::post('/{task}/labels', [BoardTaskController::class, 'addLabel']);
        Route::post('/{task}/watchers', [BoardTaskController::class, 'addWatcher']);
        Route::post('/{task}/dependencies', [BoardTaskController::class, 'addDependency']);
    });

    // Sprints
    Route::prefix('sprints')->group(function () {
        Route::get('/', [SprintController::class, 'index']);
        Route::post('/', [SprintController::class, 'store'])->middleware('check.permission:taskboard.sprints.create');
        Route::get('/{sprint}', [SprintController::class, 'show']);
        Route::put('/{sprint}', [SprintController::class, 'update']);
        Route::delete('/{sprint}', [SprintController::class, 'destroy']);
        Route::post('/{sprint}/start', [SprintController::class, 'start']);
        Route::post('/{sprint}/complete', [SprintController::class, 'complete']);
        Route::post('/{sprint}/tasks', [SprintController::class, 'addTasks']);
        Route::delete('/{sprint}/tasks', [SprintController::class, 'removeTasks']);
        Route::get('/{sprint}/burndown', [SprintController::class, 'burndownChart']);
    });
