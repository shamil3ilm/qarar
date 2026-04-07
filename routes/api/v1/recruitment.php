<?php

use App\Http\Controllers\Api\V1\HR\RecruitmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR Recruitment API Routes
|--------------------------------------------------------------------------
|
| All routes are nested under /api/v1/hr/recruitment (via api.php include)
|
*/

Route::prefix('recruitment')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Job Postings
    |--------------------------------------------------------------------------
    */
    Route::prefix('job-postings')->group(function () {
        Route::get('/', [RecruitmentController::class, 'indexJobPostings'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.job-postings.index');

        Route::post('/', [RecruitmentController::class, 'storeJobPosting'])
            ->middleware('check.permission:hr.recruitment.create')
            ->name('hr.recruitment.job-postings.store');

        Route::get('/{id}', [RecruitmentController::class, 'showJobPosting'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.job-postings.show');

        Route::put('/{id}', [RecruitmentController::class, 'updateJobPosting'])
            ->middleware('check.permission:hr.recruitment.edit')
            ->name('hr.recruitment.job-postings.update');

        Route::delete('/{id}', [RecruitmentController::class, 'destroyJobPosting'])
            ->middleware('check.permission:hr.recruitment.delete')
            ->name('hr.recruitment.job-postings.destroy');

        Route::post('/{id}/publish', [RecruitmentController::class, 'publishJobPosting'])
            ->middleware('check.permission:hr.recruitment.publish')
            ->name('hr.recruitment.job-postings.publish');

        Route::post('/{id}/close', [RecruitmentController::class, 'closeJobPosting'])
            ->middleware('check.permission:hr.recruitment.edit')
            ->name('hr.recruitment.job-postings.close');

        // Apply to a specific job posting
        Route::post('/{id}/apply', [RecruitmentController::class, 'applyForJob'])
            ->middleware('check.permission:hr.recruitment.applications.create')
            ->name('hr.recruitment.job-postings.apply');

        // Interviews under a specific application (nested for posting context)
        Route::post('/{id}/applications/{applicationId}/interviews', [RecruitmentController::class, 'scheduleInterview'])
            ->middleware('check.permission:hr.recruitment.interviews.create')
            ->name('hr.recruitment.job-postings.interviews.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Candidates
    |--------------------------------------------------------------------------
    */
    Route::prefix('candidates')->group(function () {
        Route::get('/', [RecruitmentController::class, 'indexCandidates'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.candidates.index');

        Route::post('/', [RecruitmentController::class, 'storeCandidate'])
            ->middleware('check.permission:hr.recruitment.create')
            ->name('hr.recruitment.candidates.store');

        Route::get('/{id}', [RecruitmentController::class, 'showCandidate'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.candidates.show');

        Route::put('/{id}', [RecruitmentController::class, 'updateCandidate'])
            ->middleware('check.permission:hr.recruitment.edit')
            ->name('hr.recruitment.candidates.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    */
    Route::prefix('applications')->group(function () {
        Route::get('/', [RecruitmentController::class, 'indexApplications'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.applications.index');

        Route::get('/{id}', [RecruitmentController::class, 'showApplication'])
            ->middleware('check.permission:hr.recruitment.view')
            ->name('hr.recruitment.applications.show');

        Route::post('/{id}/shortlist', [RecruitmentController::class, 'shortlistApplication'])
            ->middleware('check.permission:hr.recruitment.edit')
            ->name('hr.recruitment.applications.shortlist');

        Route::post('/{id}/reject', [RecruitmentController::class, 'rejectApplication'])
            ->middleware('check.permission:hr.recruitment.edit')
            ->name('hr.recruitment.applications.reject');

        Route::post('/{id}/convert-to-employee', [RecruitmentController::class, 'convertToEmployee'])
            ->middleware('check.permission:hr.recruitment.convert')
            ->name('hr.recruitment.applications.convert-to-employee');

        // Interviews under an application
        Route::post('/{id}/interviews', [RecruitmentController::class, 'scheduleInterview'])
            ->middleware('check.permission:hr.recruitment.interviews.create')
            ->name('hr.recruitment.applications.interviews.store');

        // Offers under an application
        Route::post('/{id}/offers', [RecruitmentController::class, 'createJobOffer'])
            ->middleware('check.permission:hr.recruitment.offers.create')
            ->name('hr.recruitment.applications.offers.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Interviews (standalone resource for feedback)
    |--------------------------------------------------------------------------
    */
    Route::prefix('interviews')->group(function () {
        Route::put('/{id}/feedback', [RecruitmentController::class, 'recordInterviewFeedback'])
            ->middleware('check.permission:hr.recruitment.interviews.edit')
            ->name('hr.recruitment.interviews.feedback');
    });

    /*
    |--------------------------------------------------------------------------
    | Offers (standalone resource for lifecycle actions)
    |--------------------------------------------------------------------------
    */
    Route::prefix('offers')->group(function () {
        Route::post('/{id}/send', [RecruitmentController::class, 'sendOffer'])
            ->middleware('check.permission:hr.recruitment.offers.send')
            ->name('hr.recruitment.offers.send');

        Route::post('/{id}/accept', [RecruitmentController::class, 'acceptOffer'])
            ->middleware('check.permission:hr.recruitment.offers.manage')
            ->name('hr.recruitment.offers.accept');

        Route::post('/{id}/decline', [RecruitmentController::class, 'declineOffer'])
            ->middleware('check.permission:hr.recruitment.offers.manage')
            ->name('hr.recruitment.offers.decline');
    });
});
