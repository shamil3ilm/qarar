<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\AccrualDeferralController;
use App\Http\Controllers\Api\V1\Accounting\AccountStatementController;
use App\Http\Controllers\Api\V1\Accounting\ArInterestRunController;
use App\Http\Controllers\Api\V1\Accounting\CarryForwardController;
use App\Http\Controllers\Api\V1\Accounting\DisputeManagementController;
use App\Http\Controllers\Api\V1\Accounting\DocumentSplittingController;
use App\Http\Controllers\Api\V1\Accounting\ParkedDocumentController;
use App\Http\Controllers\Api\V1\Accounting\PaymentRunController;
use App\Http\Controllers\Api\V1\Accounting\PostingValidationRuleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('accruals-deferrals', AccrualDeferralController::class)->names('accounting.accruals');
Route::post('accruals-deferrals/{accrualDeferral}/post-period', [AccrualDeferralController::class, 'postPeriod'])
    ->name('accounting.accruals.post-period');

Route::post('carry-forward/execute', [CarryForwardController::class, 'execute'])
    ->name('accounting.carry-forward.execute');
Route::get('carry-forward/{carryForwardRun}', [CarryForwardController::class, 'status'])
    ->name('accounting.carry-forward.status');

Route::apiResource('payment-runs', PaymentRunController::class)->names('accounting.payment-runs');
Route::post('payment-runs/{paymentRun}/approve', [PaymentRunController::class, 'approve'])
    ->name('accounting.payment-runs.approve');
Route::post('payment-runs/{paymentRun}/post', [PaymentRunController::class, 'post'])
    ->name('accounting.payment-runs.post');
Route::post('payment-runs/{paymentRun}/cancel', [PaymentRunController::class, 'cancel'])
    ->name('accounting.payment-runs.cancel');
Route::put('payment-runs/{paymentRun}/items/{paymentRunItem}/exclude', [PaymentRunController::class, 'excludeItem'])
    ->name('accounting.payment-runs.items.exclude');

Route::prefix('disputes')->group(function () {
    Route::get('/', [DisputeManagementController::class, 'index'])->name('accounting.disputes.index');
    Route::post('/', [DisputeManagementController::class, 'store'])->name('accounting.disputes.store');
    Route::get('/collections-worklist', [DisputeManagementController::class, 'collectionsWorklist'])
        ->name('accounting.disputes.collections-worklist');
    Route::post('/promise-to-pay', [DisputeManagementController::class, 'promiseToPay'])
        ->name('accounting.disputes.promise-to-pay');
    Route::get('/{disputeCase}', [DisputeManagementController::class, 'show'])->name('accounting.disputes.show');
    Route::put('/{disputeCase}', [DisputeManagementController::class, 'update'])->name('accounting.disputes.update');
    Route::post('/{disputeCase}/resolve', [DisputeManagementController::class, 'resolve'])
        ->name('accounting.disputes.resolve');
    Route::post('/{disputeCase}/close', [DisputeManagementController::class, 'close'])
        ->name('accounting.disputes.close');
});

Route::apiResource('parked-documents', ParkedDocumentController::class)->names('accounting.parked-documents');
Route::post('parked-documents/{parkedDocument}/approve', [ParkedDocumentController::class, 'approve'])
    ->name('accounting.parked-documents.approve');
Route::post('parked-documents/{parkedDocument}/post', [ParkedDocumentController::class, 'post'])
    ->name('accounting.parked-documents.post');

// ----------------------------------------------------------------
// FI-GL Document Splitting — Gap 1
// ----------------------------------------------------------------

Route::post('document-splitting-rules/preview', [DocumentSplittingController::class, 'splitPreview'])
    ->name('accounting.document-splitting-rules.preview');

Route::apiResource('document-splitting-rules', DocumentSplittingController::class)
    ->only(['index', 'store', 'update', 'destroy'])
    ->names('accounting.document-splitting-rules');

// Posting Validation & Substitution Rules
Route::post('posting-validation-rules/evaluate', [PostingValidationRuleController::class, 'evaluate'])
    ->name('accounting.posting-validation-rules.evaluate');

Route::apiResource('posting-validation-rules', PostingValidationRuleController::class)
    ->names('accounting.posting-validation-rules');

// ----------------------------------------------------------------
// Gap 16: Account Statements & Open Item Reconciliation
// ----------------------------------------------------------------
Route::prefix('statements')->group(function (): void {
    Route::get('customers/{contactId}', [AccountStatementController::class, 'customerStatement'])
        ->name('accounting.statements.customer');
    Route::get('vendors/{contactId}', [AccountStatementController::class, 'vendorStatement'])
        ->name('accounting.statements.vendor');
    Route::post('send', [AccountStatementController::class, 'sendStatement'])
        ->name('accounting.statements.send');
    Route::get('open-items', [AccountStatementController::class, 'openItems'])
        ->name('accounting.statements.open-items');
    Route::post('confirm-reconciliation', [AccountStatementController::class, 'confirmReconciliation'])
        ->name('accounting.statements.confirm-reconciliation');
});

// ----------------------------------------------------------------
// FI-AR Interest on Overdue Receivables (SAP F.24/F.26)
// ----------------------------------------------------------------
Route::get('ar-interest-runs/preview', [ArInterestRunController::class, 'preview'])
    ->name('accounting.ar-interest-runs.preview');
Route::post('ar-interest-runs/execute', [ArInterestRunController::class, 'execute'])
    ->name('accounting.ar-interest-runs.execute');
