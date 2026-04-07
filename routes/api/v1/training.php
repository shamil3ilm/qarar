<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\TrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Training & Development Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/hr/training
|
*/

// Training Providers
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/providers', [TrainingController::class, 'indexProviders'])->name('hr.training.providers.index');
    Route::get('/providers/{id}', [TrainingController::class, 'showProvider'])->name('hr.training.providers.show');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::post('/providers', [TrainingController::class, 'storeProvider'])->name('hr.training.providers.store');
    Route::put('/providers/{id}', [TrainingController::class, 'updateProvider'])->name('hr.training.providers.update');
    Route::delete('/providers/{id}', [TrainingController::class, 'destroyProvider'])->name('hr.training.providers.destroy');
});

// Training Courses
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/courses', [TrainingController::class, 'indexCourses'])->name('hr.training.courses.index');
    Route::get('/courses/{id}', [TrainingController::class, 'showCourse'])->name('hr.training.courses.show');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::post('/courses', [TrainingController::class, 'storeCourse'])->name('hr.training.courses.store');
    Route::put('/courses/{id}', [TrainingController::class, 'updateCourse'])->name('hr.training.courses.update');
    Route::delete('/courses/{id}', [TrainingController::class, 'destroyCourse'])->name('hr.training.courses.destroy');
});

// Training Sessions
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/sessions', [TrainingController::class, 'indexSessions'])->name('hr.training.sessions.index');
    Route::get('/sessions/{id}', [TrainingController::class, 'showSession'])->name('hr.training.sessions.show');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::post('/sessions', [TrainingController::class, 'storeSession'])->name('hr.training.sessions.store');
    Route::put('/sessions/{id}', [TrainingController::class, 'updateSession'])->name('hr.training.sessions.update');
    Route::post('/sessions/{id}/start', [TrainingController::class, 'startSession'])->name('hr.training.sessions.start');
    Route::post('/sessions/{id}/complete', [TrainingController::class, 'completeSession'])->name('hr.training.sessions.complete');
    Route::post('/sessions/{id}/cancel', [TrainingController::class, 'cancelSession'])->name('hr.training.sessions.cancel');
    Route::post('/sessions/{id}/enroll', [TrainingController::class, 'enroll'])->name('hr.training.sessions.enroll');
    Route::post('/sessions/{id}/bulk-enroll', [TrainingController::class, 'bulkEnroll'])->name('hr.training.sessions.bulk-enroll');
    Route::delete('/sessions/{sessionId}/enrollments/{enrollmentId}', [TrainingController::class, 'cancelEnrollment'])->name('hr.training.sessions.enrollments.cancel');
});

// Enrollments
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/enrollments', [TrainingController::class, 'indexEnrollments'])->name('hr.training.enrollments.index');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::delete('/enrollments/{id}', [TrainingController::class, 'cancelEnrollment'])->name('hr.training.enrollments.cancel');
});

// Certifications
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/certifications', [TrainingController::class, 'indexCertifications'])->name('hr.training.certifications.index');
    Route::get('/certifications/expiring', [TrainingController::class, 'expiringCertifications'])->name('hr.training.certifications.expiring');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::post('/certifications', [TrainingController::class, 'storeCertification'])->name('hr.training.certifications.store');
    Route::post('/certifications/enrollments/{enrollmentId}/issue', [TrainingController::class, 'issueCertificate'])->name('hr.training.certifications.issue');
});

// Training Needs
Route::middleware(['check.permission:hr.training.view'])->group(function () {
    Route::get('/needs', [TrainingController::class, 'indexNeeds'])->name('hr.training.needs.index');
});

Route::middleware(['check.permission:hr.training.manage'])->group(function () {
    Route::post('/needs', [TrainingController::class, 'storeNeed'])->name('hr.training.needs.store');
    Route::put('/needs/{id}', [TrainingController::class, 'updateNeed'])->name('hr.training.needs.update');
    Route::delete('/needs/{id}', [TrainingController::class, 'destroyNeed'])->name('hr.training.needs.destroy');
});

// Reports
Route::middleware(['check.permission:hr.training.reports'])->group(function () {
    Route::get('/reports/compliance', [TrainingController::class, 'mandatoryComplianceReport'])->name('hr.training.reports.compliance');
    Route::get('/reports/calendar', [TrainingController::class, 'trainingCalendar'])->name('hr.training.reports.calendar');
});
