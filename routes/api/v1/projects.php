<?php

use App\Http\Controllers\Api\V1\Projects\EvmBaselineController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectInvoicingController;
use App\Http\Controllers\Api\V1\Projects\ProjectResourceController;
use App\Http\Controllers\Api\V1\Projects\ProjectRevenuePlanController;
use App\Http\Controllers\Api\V1\Projects\ProjectTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project Systems API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Projects CRUD
    |--------------------------------------------------------------------------
    */
    Route::prefix('projects')->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])
            ->middleware('check.permission:projects.projects.view');

        Route::post('/', [ProjectController::class, 'store'])
            ->middleware('check.permission:projects.projects.create');

        Route::get('/{project}', [ProjectController::class, 'show'])
            ->middleware('check.permission:projects.projects.view');

        Route::put('/{project}', [ProjectController::class, 'update'])
            ->middleware('check.permission:projects.projects.edit');

        Route::delete('/{project}', [ProjectController::class, 'destroy'])
            ->middleware('check.permission:projects.projects.delete');

        // Status actions
        Route::post('/{project}/activate', [ProjectController::class, 'activateProject'])
            ->middleware('check.permission:projects.projects.edit');

        Route::post('/{project}/complete', [ProjectController::class, 'completeProject'])
            ->middleware('check.permission:projects.projects.edit');

        // Dashboard
        Route::get('/{project}/dashboard', [ProjectController::class, 'dashboard'])
            ->middleware('check.permission:projects.projects.view');

        // Gap 1: CPM Scheduling
        Route::get('/{project}/critical-path', [ProjectController::class, 'criticalPath'])
            ->middleware('check.permission:projects.projects.view')
            ->name('projects.critical-path');

        Route::get('/{project}/schedule-forecast', [ProjectController::class, 'scheduleForecast'])
            ->middleware('check.permission:projects.projects.view')
            ->name('projects.schedule-forecast');

        // Gap 2: Cost Variance Reports
        Route::get('/{project}/cost-variance', [ProjectController::class, 'costVariance'])
            ->middleware('check.permission:projects.costs.view')
            ->name('projects.cost-variance');

        Route::get('/{project}/cost-trend', [ProjectController::class, 'costTrend'])
            ->middleware('check.permission:projects.costs.view')
            ->name('projects.cost-trend');

        Route::get('/{project}/cost-by-type', [ProjectController::class, 'costByType'])
            ->middleware('check.permission:projects.costs.view')
            ->name('projects.cost-by-type');

        // Gap 3: EVM Trending & Forecasting
        Route::post('/{project}/evm/snapshot', [ProjectController::class, 'evmSnapshot'])
            ->middleware('check.permission:projects.projects.edit')
            ->name('projects.evm.snapshot');

        Route::get('/{project}/evm/trend', [ProjectController::class, 'evmTrend'])
            ->middleware('check.permission:projects.projects.view')
            ->name('projects.evm.trend');

        /*
        |----------------------------------------------------------------------
        | WBS Elements
        |----------------------------------------------------------------------
        */
        Route::prefix('/{project}/wbs')->group(function (): void {
            Route::get('/', [ProjectController::class, 'wbsIndex'])
                ->middleware('check.permission:projects.wbs.view');

            Route::post('/', [ProjectController::class, 'wbsStore'])
                ->middleware('check.permission:projects.wbs.create');

            Route::get('/{wbsElement}', [ProjectController::class, 'wbsShow'])
                ->middleware('check.permission:projects.wbs.view');

            Route::put('/{wbsElement}', [ProjectController::class, 'wbsUpdate'])
                ->middleware('check.permission:projects.wbs.edit');

            Route::post('/{wbsElement}/progress', [ProjectController::class, 'updateProgress'])
                ->middleware('check.permission:projects.wbs.edit');
        });

        /*
        |----------------------------------------------------------------------
        | Milestones (project-scoped)
        |----------------------------------------------------------------------
        */
        Route::prefix('/{project}/milestones')->group(function (): void {
            Route::get('/', [ProjectController::class, 'milestonesIndex'])
                ->middleware('check.permission:projects.milestones.view');

            Route::post('/', [ProjectController::class, 'milestonesStore'])
                ->middleware('check.permission:projects.milestones.create');
        });

        /*
        |----------------------------------------------------------------------
        | Time Entries (project-scoped)
        |----------------------------------------------------------------------
        */
        Route::prefix('/{project}/time-entries')->group(function (): void {
            Route::get('/', [ProjectController::class, 'timeEntriesIndex'])
                ->middleware('check.permission:projects.time.view');

            Route::post('/', [ProjectController::class, 'timeEntriesStore'])
                ->middleware('check.permission:projects.time.create');
        });

        /*
        |----------------------------------------------------------------------
        | Cost Entries (project-scoped)
        |----------------------------------------------------------------------
        */
        Route::prefix('/{project}/cost-entries')->group(function (): void {
            Route::get('/', [ProjectController::class, 'costEntriesIndex'])
                ->middleware('check.permission:projects.costs.view');

            Route::post('/', [ProjectController::class, 'costEntriesStore'])
                ->middleware('check.permission:projects.costs.create');
        });

        /*
        |----------------------------------------------------------------------
        | Members (project-scoped)
        |----------------------------------------------------------------------
        */
        Route::prefix('/{project}/members')->group(function (): void {
            Route::get('/', [ProjectController::class, 'membersIndex'])
                ->middleware('check.permission:projects.members.view');

            Route::post('/', [ProjectController::class, 'membersStore'])
                ->middleware('check.permission:projects.members.create');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Standalone resource routes (by ID, not project-scoped URL)
    |--------------------------------------------------------------------------
    */

    // Milestone update / achieve
    Route::put('/milestones/{milestone}', [ProjectController::class, 'milestonesUpdate'])
        ->middleware('check.permission:projects.milestones.edit');

    Route::post('/milestones/{milestone}/achieve', [ProjectController::class, 'achieveMilestone'])
        ->middleware('check.permission:projects.milestones.edit');

    // Time entry approval
    Route::post('/time-entries/{timeEntry}/approve', [ProjectController::class, 'approveTime'])
        ->middleware('check.permission:projects.time.approve');

    // Member removal
    Route::delete('/members/{member}', [ProjectController::class, 'membersDestroy'])
        ->middleware('check.permission:projects.members.delete');

    /*
    |--------------------------------------------------------------------------
    | EVM Baselines (PS-BAS) — SAP PS baseline management
    |--------------------------------------------------------------------------
    */
    Route::prefix('projects/{project}/baselines')->name('projects.baselines.')->group(function (): void {
        Route::get('/', [EvmBaselineController::class, 'index'])->name('index');
        Route::post('/', [EvmBaselineController::class, 'store'])->name('store');
        Route::get('/compare', [EvmBaselineController::class, 'compare'])->name('compare');
    });

    Route::prefix('baselines')->name('projects.baselines.')->group(function (): void {
        Route::get('/{baseline}', [EvmBaselineController::class, 'show'])->name('show');
        Route::post('/{baseline}/approve', [EvmBaselineController::class, 'approve'])->name('approve');
        Route::post('/{baseline}/activate', [EvmBaselineController::class, 'activate'])->name('activate');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Templates (PS-ST)
    |--------------------------------------------------------------------------
    */
    Route::prefix('templates')->name('projects.templates.')->group(function (): void {
        Route::get('/', [ProjectTemplateController::class, 'index'])->name('index');
        Route::post('/', [ProjectTemplateController::class, 'store'])->name('store');
        Route::get('/{id}', [ProjectTemplateController::class, 'show'])->name('show');
        Route::put('/{id}', [ProjectTemplateController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProjectTemplateController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/tree', [ProjectTemplateController::class, 'tree'])->name('tree');
        Route::post('/{id}/create-project', [ProjectTemplateController::class, 'createProject'])->name('create-project');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Invoicing & Revenue Recognition (PS-BIL)
    |--------------------------------------------------------------------------
    */
    Route::prefix('billing-rules')->name('projects.billing.')->group(function (): void {
        Route::get('/', [ProjectInvoicingController::class, 'billingRules'])->name('index');
        Route::post('/', [ProjectInvoicingController::class, 'storeBillingRule'])->name('store');
        Route::post('/{ruleId}/milestones', [ProjectInvoicingController::class, 'addMilestone'])->name('milestones.store');
    });

    Route::post('/revenue-recognitions', [ProjectInvoicingController::class, 'recognizeRevenue'])
        ->name('projects.revenue-recognition.store');

    /*
    |--------------------------------------------------------------------------
    | Project Resource Planning (PS-RES)
    |--------------------------------------------------------------------------
    */
    Route::prefix('resource-plans')->name('projects.resource-plans.')->group(function (): void {
        Route::get('/', [ProjectResourceController::class, 'resourcePlans'])->name('index');
        Route::post('/', [ProjectResourceController::class, 'storeResourcePlan'])->name('store');
        Route::get('/{projectId}/utilization', [ProjectResourceController::class, 'utilization'])->name('utilization');
    });

    Route::prefix('timesheets')->name('projects.timesheets.')->group(function (): void {
        Route::get('/', [ProjectResourceController::class, 'timesheets'])->name('index');
        Route::post('/', [ProjectResourceController::class, 'submitTimesheet'])->name('store');
        Route::post('/{id}/approve', [ProjectResourceController::class, 'approveTimesheet'])->name('approve');
    });

    /*
    |--------------------------------------------------------------------------
    | Project Revenue Planning (PS-FIN)
    |--------------------------------------------------------------------------
    */
    Route::prefix('revenue-plans')->name('projects.revenue-plans.')->group(function (): void {
        Route::get('/', [ProjectRevenuePlanController::class, 'index'])->name('index');
        Route::post('/', [ProjectRevenuePlanController::class, 'store'])->name('store');
        Route::get('/{id}', [ProjectRevenuePlanController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [ProjectRevenuePlanController::class, 'approve'])->name('approve');
        Route::get('/{projectId}/variance', [ProjectRevenuePlanController::class, 'variance'])->name('variance');
    });
});
