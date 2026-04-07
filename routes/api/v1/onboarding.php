<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Onboarding & Feature Adoption Routes
|--------------------------------------------------------------------------
|
| Routes for user onboarding checklists and feature adoption tracking.
| All routes require authentication and organization context.
|
*/

// Onboarding templates
Route::get('/templates', [OnboardingController::class, 'indexTemplates'])
    ->name('onboarding.templates.index');

Route::post('/templates', [OnboardingController::class, 'storeTemplate'])
    ->middleware('check.permission:core.settings.edit')
    ->name('onboarding.templates.store');

Route::post('/templates/{templateId}/steps', [OnboardingController::class, 'addStep'])
    ->middleware('check.permission:core.settings.edit')
    ->name('onboarding.templates.steps.add');

// User progress
Route::get('/progress/{userId}', [OnboardingController::class, 'getUserProgress'])
    ->name('onboarding.progress.show');

// Step actions (for authenticated user's own progress)
Route::post('/steps/{stepId}/complete', [OnboardingController::class, 'completeStep'])
    ->name('onboarding.steps.complete');

Route::post('/steps/{stepId}/skip', [OnboardingController::class, 'skipStep'])
    ->name('onboarding.steps.skip');

// Feature adoption tracking
Route::post('/features/track', [OnboardingController::class, 'trackFeature'])
    ->name('onboarding.features.track');

Route::get('/adoption-summary', [OnboardingController::class, 'adoptionSummary'])
    ->middleware('check.permission:core.settings.view')
    ->name('onboarding.adoption-summary');
