<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\WorkflowEscalationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workflow Escalation & Substitution Routes (Platform)
|--------------------------------------------------------------------------
*/

Route::prefix('workflow-escalation')->name('core.workflow-escalation.')->group(function () {
    Route::get('/rules', [WorkflowEscalationController::class, 'indexRules'])->name('rules.index');
    Route::post('/rules', [WorkflowEscalationController::class, 'storeRule'])->name('rules.store');
    Route::put('/rules/{id}', [WorkflowEscalationController::class, 'updateRule'])->name('rules.update');
    Route::delete('/rules/{id}', [WorkflowEscalationController::class, 'destroyRule'])->name('rules.destroy');
    Route::post('/check-and-escalate', [WorkflowEscalationController::class, 'checkAndEscalate'])->name('check');
    Route::get('/substitutions', [WorkflowEscalationController::class, 'indexSubstitutions'])->name('substitutions.index');
    Route::post('/substitutions', [WorkflowEscalationController::class, 'createSubstitution'])->name('substitutions.store');
    Route::delete('/substitutions/{id}', [WorkflowEscalationController::class, 'revokeSubstitution'])->name('substitutions.revoke');
    Route::get('/substitute/{approverId}', [WorkflowEscalationController::class, 'getSubstitute'])->name('get-substitute');
});
