<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\BenefitsController;
use App\Http\Controllers\Api\V1\HR\EosbController;
use App\Http\Controllers\Api\V1\HR\ShiftPlanningController;
use App\Http\Controllers\Api\V1\HR\SocialInsuranceController;
use App\Http\Controllers\Api\V1\HR\SocialInsuranceExportController;
use App\Http\Controllers\Api\V1\HR\SuccessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'validate.jwt', 'check.organization'])->group(function () {

    // -------------------------------------------------------------------------
    // EOSB / Gratuity
    // -------------------------------------------------------------------------
    Route::prefix('hr/eosb')->name('hr.eosb.')->group(function () {
        Route::get('policies', [EosbController::class, 'index'])
            ->middleware('check.permission:hr.eosb.view')
            ->name('policies.index');

        Route::post('policies', [EosbController::class, 'store'])
            ->middleware('check.permission:hr.eosb.manage')
            ->name('policies.store');

        Route::get('policies/{eosbPolicy}', [EosbController::class, 'show'])
            ->middleware('check.permission:hr.eosb.view')
            ->name('policies.show');

        Route::put('policies/{eosbPolicy}', [EosbController::class, 'update'])
            ->middleware('check.permission:hr.eosb.manage')
            ->name('policies.update');

        Route::get('settlements', [EosbController::class, 'settlements'])
            ->middleware('check.permission:hr.eosb.view')
            ->name('settlements.index');

        Route::post('settlements/{settlement}/approve', [EosbController::class, 'approveSettlement'])
            ->middleware('check.permission:hr.eosb.manage')
            ->name('settlements.approve');

        Route::post('settlements/{settlement}/pay', [EosbController::class, 'markSettlementPaid'])
            ->middleware('check.permission:hr.eosb.manage')
            ->name('settlements.pay');

        Route::get('employees/{employee}/provisions', [EosbController::class, 'showEmployeeProvisions'])
            ->middleware('check.permission:hr.eosb.view')
            ->name('employees.provisions');

        Route::post('employees/{employee}/calculate-settlement', [EosbController::class, 'calculateSettlement'])
            ->middleware('check.permission:hr.eosb.view')
            ->name('employees.calculate-settlement');

        Route::post('employees/{employee}/settlements', [EosbController::class, 'storeSettlement'])
            ->middleware('check.permission:hr.eosb.manage')
            ->name('employees.settlements.store');
    });

    // -------------------------------------------------------------------------
    // Social Insurance (GOSI / DEWS / GPSSA)
    // -------------------------------------------------------------------------
    Route::prefix('social-insurance')->name('hr.social-insurance.')->group(function () {
        Route::get('schemes', [SocialInsuranceController::class, 'index'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('schemes.index');

        Route::post('schemes', [SocialInsuranceController::class, 'store'])
            ->middleware('check.permission:hr.social-insurance.manage')
            ->name('schemes.store');

        Route::get('schemes/{scheme}', [SocialInsuranceController::class, 'show'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('schemes.show');

        Route::put('schemes/{scheme}', [SocialInsuranceController::class, 'update'])
            ->middleware('check.permission:hr.social-insurance.manage')
            ->name('schemes.update');

        Route::post('schemes/{scheme}/enroll', [SocialInsuranceController::class, 'enroll'])
            ->middleware('check.permission:hr.social-insurance.manage')
            ->name('schemes.enroll');

        Route::get('schemes/{scheme}/records', [SocialInsuranceController::class, 'listRecords'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('schemes.records');

        Route::post('schemes/{scheme}/generate-submission', [SocialInsuranceController::class, 'generateSubmission'])
            ->middleware('check.permission:hr.social-insurance.manage')
            ->name('schemes.generate-submission');

        Route::get('submissions', [SocialInsuranceController::class, 'indexSubmissions'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('submissions.index');

        Route::get('submissions/{submission}', [SocialInsuranceController::class, 'showSubmission'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('submissions.show');

        Route::post('submissions/{submission}/submit', [SocialInsuranceController::class, 'submitSubmission'])
            ->middleware('check.permission:hr.social-insurance.manage')
            ->name('submissions.submit');

        Route::get('submissions/{submission}/export', [SocialInsuranceExportController::class, 'export'])
            ->middleware('check.permission:hr.social-insurance.view')
            ->name('submissions.export');
    });

    // -------------------------------------------------------------------------
    // Employee Benefits
    // -------------------------------------------------------------------------
    Route::prefix('hr/benefits')->name('hr.benefits.')->group(function () {
        Route::get('types', [BenefitsController::class, 'index'])
            ->middleware('check.permission:hr.benefits.view')
            ->name('types.index');

        Route::post('types', [BenefitsController::class, 'store'])
            ->middleware('check.permission:hr.benefits.manage')
            ->name('types.store');

        Route::get('types/{benefitType}', [BenefitsController::class, 'showType'])
            ->middleware('check.permission:hr.benefits.view')
            ->name('types.show');

        Route::put('types/{benefitType}', [BenefitsController::class, 'updateType'])
            ->middleware('check.permission:hr.benefits.manage')
            ->name('types.update');

        Route::get('employees/{employee}', [BenefitsController::class, 'listEmployeeBenefits'])
            ->middleware('check.permission:hr.benefits.view')
            ->name('employees.list');

        Route::post('employees/{employee}/enroll', [BenefitsController::class, 'enroll'])
            ->middleware('check.permission:hr.benefits.manage')
            ->name('employees.enroll');

        Route::put('{employeeBenefit}', [BenefitsController::class, 'update'])
            ->middleware('check.permission:hr.benefits.manage')
            ->name('update');

        Route::post('{employeeBenefit}/terminate', [BenefitsController::class, 'terminate'])
            ->middleware('check.permission:hr.benefits.manage')
            ->name('terminate');

        Route::get('{employeeBenefit}/history', [BenefitsController::class, 'changeHistory'])
            ->middleware('check.permission:hr.benefits.view')
            ->name('history');
    });

    // -------------------------------------------------------------------------
    // Shift Planning / Roster
    // -------------------------------------------------------------------------
    Route::prefix('hr/shifts')->name('hr.shifts.')->group(function () {
        Route::get('patterns', [ShiftPlanningController::class, 'indexPatterns'])
            ->middleware('check.permission:hr.shifts.view')
            ->name('patterns.index');

        Route::post('patterns', [ShiftPlanningController::class, 'storePattern'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('patterns.store');

        Route::put('patterns/{shiftPattern}', [ShiftPlanningController::class, 'updatePattern'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('patterns.update');

        Route::get('rosters', [ShiftPlanningController::class, 'indexRosters'])
            ->middleware('check.permission:hr.shifts.view')
            ->name('rosters.index');

        Route::post('rosters', [ShiftPlanningController::class, 'storeRoster'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('rosters.store');

        Route::get('rosters/{shiftRoster}', [ShiftPlanningController::class, 'showRoster'])
            ->middleware('check.permission:hr.shifts.view')
            ->name('rosters.show');

        Route::post('rosters/{shiftRoster}/assign', [ShiftPlanningController::class, 'assignShift'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('rosters.assign');

        Route::post('rosters/{shiftRoster}/bulk-assign', [ShiftPlanningController::class, 'bulkAssign'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('rosters.bulk-assign');

        Route::post('rosters/{shiftRoster}/publish', [ShiftPlanningController::class, 'publishRoster'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('rosters.publish');

        Route::get('swaps', [ShiftPlanningController::class, 'listSwapRequests'])
            ->middleware('check.permission:hr.shifts.view')
            ->name('swaps.index');

        Route::post('swaps', [ShiftPlanningController::class, 'requestSwap'])
            ->middleware('check.permission:hr.shifts.request-swap')
            ->name('swaps.request');

        Route::post('swaps/{shiftSwapRequest}/approve', [ShiftPlanningController::class, 'approveSwap'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('swaps.approve');

        Route::post('swaps/{shiftSwapRequest}/reject', [ShiftPlanningController::class, 'rejectSwap'])
            ->middleware('check.permission:hr.shifts.manage')
            ->name('swaps.reject');
    });

    // -------------------------------------------------------------------------
    // Succession Planning
    // -------------------------------------------------------------------------
    Route::prefix('hr/succession')->name('hr.succession.')->group(function () {
        Route::get('summary', [SuccessionController::class, 'summary'])
            ->middleware('check.permission:hr.succession.view')
            ->name('summary');

        Route::get('positions', [SuccessionController::class, 'indexKeyPositions'])
            ->middleware('check.permission:hr.succession.view')
            ->name('positions.index');

        Route::post('positions', [SuccessionController::class, 'storeKeyPosition'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('positions.store');

        Route::get('positions/{keyPosition}', [SuccessionController::class, 'showKeyPosition'])
            ->middleware('check.permission:hr.succession.view')
            ->name('positions.show');

        Route::put('positions/{keyPosition}', [SuccessionController::class, 'updateKeyPosition'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('positions.update');

        Route::get('positions/{keyPosition}/candidates', [SuccessionController::class, 'indexCandidates'])
            ->middleware('check.permission:hr.succession.view')
            ->name('positions.candidates.index');

        Route::post('positions/{keyPosition}/candidates', [SuccessionController::class, 'nominateCandidate'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('positions.candidates.nominate');

        Route::put('candidates/{candidate}/readiness', [SuccessionController::class, 'updateReadiness'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('candidates.readiness');

        Route::post('candidates/{candidate}/deactivate', [SuccessionController::class, 'deactivateCandidate'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('candidates.deactivate');

        Route::post('candidates/{candidate}/activities', [SuccessionController::class, 'addActivity'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('candidates.activities.store');

        Route::put('activities/{activity}', [SuccessionController::class, 'updateActivity'])
            ->middleware('check.permission:hr.succession.manage')
            ->name('activities.update');
    });

});
