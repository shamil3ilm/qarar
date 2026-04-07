<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Compliance\OnboardingController;
use App\Http\Controllers\Api\V1\Core\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

// GRC — Governance, Risk & Compliance (SAP GRC-IA / GRC-AC / GRC-PC / CCM)
Route::prefix('grc')->group(function (): void {
    require __DIR__.'/grc.php';
});

Route::prefix('compliance')->group(function (): void {
    Route::prefix('branches/{branchId}/onboarding')->group(function (): void {
        Route::get('/status', [OnboardingController::class, 'status'])
            ->middleware('check.permission:compliance.onboarding.view');
        Route::post('/ccsid', [OnboardingController::class, 'requestCcsid'])
            ->middleware('check.permission:compliance.onboarding.manage');
        Route::post('/compliance-check', [OnboardingController::class, 'complianceCheck'])
            ->middleware('check.permission:compliance.onboarding.manage');
        Route::post('/pcsid', [OnboardingController::class, 'requestPcsid'])
            ->middleware('check.permission:compliance.onboarding.manage');
    });
});

/*
|--------------------------------------------------------------------------
| Document Download (signed, expiring links)
|--------------------------------------------------------------------------
|
| The generate endpoint requires JWT auth (inherited from the parent group).
| The download endpoint is public — authentication is provided by the token.
|
*/
Route::prefix('documents')->name('documents.secure.')->group(function (): void {
    // Authenticated: generate a signed download link.
    Route::post('/generate-link', [DocumentDownloadController::class, 'generate'])
        ->name('generate');

    // Public: stream the PDF. Exempt from the standard auth middleware stack.
    Route::get('/download/{token}', [DocumentDownloadController::class, 'download'])
        ->name('download')
        ->withoutMiddleware(['auth:api', 'validate.jwt', 'check.organization']);
});
