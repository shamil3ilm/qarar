<?php

use App\Http\Controllers\Api\V1\Automation\AutomationRuleController;
use App\Http\Controllers\Api\V1\Automation\AutomationEmailTemplateController;
use App\Http\Controllers\Api\V1\Automation\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Automation API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/automation
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Automation Rules
    |--------------------------------------------------------------------------
    */
    Route::prefix('rules')->group(function () {
        Route::get('/', [AutomationRuleController::class, 'index'])->name('automation.rules.index');
        Route::post('/', [AutomationRuleController::class, 'store'])->name('automation.rules.store');
        Route::get('/{automationRule}', [AutomationRuleController::class, 'show'])->name('automation.rules.show');
        Route::put('/{automationRule}', [AutomationRuleController::class, 'update'])->name('automation.rules.update');
        Route::delete('/{automationRule}', [AutomationRuleController::class, 'destroy'])->name('automation.rules.destroy');
        Route::patch('/{automationRule}/active', [AutomationRuleController::class, 'setActive'])->name('automation.rules.active');
        Route::post('/{automationRule}/test', [AutomationRuleController::class, 'test'])->name('automation.rules.test');
        Route::get('/{automationRule}/logs', [AutomationRuleController::class, 'logs'])->name('automation.rules.logs');
    });

    /*
    |--------------------------------------------------------------------------
    | Approval Workflows
    |--------------------------------------------------------------------------
    */
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index'])
            ->middleware('check.permission:automation.workflows.view')
            ->name('automation.workflows.index');
        Route::post('/', [WorkflowController::class, 'store'])
            ->middleware('check.permission:automation.workflows.create')
            ->name('automation.workflows.store');
        Route::get('/{workflow}', [WorkflowController::class, 'show'])
            ->middleware('check.permission:automation.workflows.view')
            ->name('automation.workflows.show');
        Route::put('/{workflow}', [WorkflowController::class, 'update'])
            ->middleware('check.permission:automation.workflows.update')
            ->name('automation.workflows.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Approval Requests
    |--------------------------------------------------------------------------
    */
    Route::prefix('approvals')->group(function () {
        Route::get('/', [WorkflowController::class, 'pendingApprovals'])
            ->middleware('check.permission:automation.approvals.view')
            ->name('automation.approvals.index');
        Route::get('/history', [WorkflowController::class, 'history'])
            ->middleware('check.permission:automation.approvals.view')
            ->name('automation.approvals.history');
        Route::post('/{approvalRequest}/approve', [WorkflowController::class, 'approve'])
            ->middleware('check.permission:automation.approvals.approve')
            ->name('automation.approvals.approve');
        Route::post('/{approvalRequest}/reject', [WorkflowController::class, 'reject'])
            ->middleware('check.permission:automation.approvals.approve')
            ->name('automation.approvals.reject');
    });

    /*
    |--------------------------------------------------------------------------
    | Automation Email Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('email-templates')->group(function () {
        Route::get('/', [AutomationEmailTemplateController::class, 'index'])->name('automation.email-templates.index');
        Route::post('/', [AutomationEmailTemplateController::class, 'store'])->name('automation.email-templates.store');
        Route::get('/{automationEmailTemplate}', [AutomationEmailTemplateController::class, 'show'])->name('automation.email-templates.show');
        Route::put('/{automationEmailTemplate}', [AutomationEmailTemplateController::class, 'update'])->name('automation.email-templates.update');
        Route::delete('/{automationEmailTemplate}', [AutomationEmailTemplateController::class, 'destroy'])->name('automation.email-templates.destroy');
        Route::post('/{automationEmailTemplate}/preview', [AutomationEmailTemplateController::class, 'preview'])->name('automation.email-templates.preview');
    });
});
