<?php

use App\Http\Controllers\Api\V1\Core\ModuleAccessController;
use Illuminate\Support\Facades\Route;

// Module definitions
Route::get('/modules', [ModuleAccessController::class, 'index']);

// Organization module access
Route::get('/organization-modules', [ModuleAccessController::class, 'orgModules']);
Route::patch('/organization-modules/{moduleId}/active', [ModuleAccessController::class, 'setModuleActive'])->middleware('check.permission:modules.manage');

// Role permissions
Route::get('/roles/{role}/permissions', [ModuleAccessController::class, 'rolePermissions']);
Route::put('/roles/{role}/permissions/{moduleId}', [ModuleAccessController::class, 'setRolePermission']);

// User overrides
Route::get('/users/{user}/overrides', [ModuleAccessController::class, 'userOverrides']);
Route::put('/users/{user}/overrides/{moduleId}', [ModuleAccessController::class, 'setUserOverride']);
Route::delete('/users/{user}/overrides/{moduleId}', [ModuleAccessController::class, 'removeUserOverride']);

// Menu & access logs
Route::get('/menu', [ModuleAccessController::class, 'menuItems']);
Route::get('/access-logs', [ModuleAccessController::class, 'accessLogs']);
