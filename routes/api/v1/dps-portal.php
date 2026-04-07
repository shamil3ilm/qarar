<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\CustomerPortalController;
use App\Http\Controllers\Api\V1\Compliance\DeniedPartyScreeningController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Denied Party Screening — requires standard JWT auth
// -------------------------------------------------------------------------
Route::middleware(['auth:api'])->group(function (): void {
    Route::prefix('compliance/dps')->name('compliance.dps.')->group(function (): void {
        Route::get('/lists', [DeniedPartyScreeningController::class, 'lists'])->name('lists');
        Route::post('/lists', [DeniedPartyScreeningController::class, 'storeList'])->name('lists.store');
        Route::get('/lists/{id}', [DeniedPartyScreeningController::class, 'showList'])->name('lists.show');
        Route::put('/lists/{id}', [DeniedPartyScreeningController::class, 'updateList'])->name('lists.update');
        Route::get('/lists/{listId}/entries', [DeniedPartyScreeningController::class, 'listEntries'])->name('entries');
        Route::post('/lists/{listId}/entries', [DeniedPartyScreeningController::class, 'storeEntry'])->name('entries.store');
        Route::post('/lists/{listId}/import', [DeniedPartyScreeningController::class, 'importEntries'])->name('entries.import');
        Route::post('/screen-contact', [DeniedPartyScreeningController::class, 'screenContact'])->name('screen-contact');
        Route::post('/screen-all', [DeniedPartyScreeningController::class, 'screenAll'])->name('screen-all');
        Route::get('/runs', [DeniedPartyScreeningController::class, 'runs'])->name('runs');
        Route::get('/runs/{id}', [DeniedPartyScreeningController::class, 'showRun'])->name('runs.show');
        Route::post('/runs/{id}/clear', [DeniedPartyScreeningController::class, 'clearRun'])->name('runs.clear');
        Route::get('/pending-reviews', [DeniedPartyScreeningController::class, 'pendingReviews'])->name('pending-reviews');
        Route::get('/contacts/{contactId}/status', [DeniedPartyScreeningController::class, 'checkContact'])->name('contacts.status');
    });
});

// -------------------------------------------------------------------------
// Customer Self-Service Portal
// Public endpoints (register / login / forgot-password / reset-password)
// do NOT require JWT — the portal uses its own session token mechanism.
// -------------------------------------------------------------------------
Route::prefix('portal')->name('portal.')->group(function (): void {
    // Public
    Route::post('/register', [CustomerPortalController::class, 'register'])->name('register');
    Route::post('/login', [CustomerPortalController::class, 'login'])->name('login');
    Route::post('/forgot-password', [CustomerPortalController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [CustomerPortalController::class, 'resetPassword'])->name('reset-password');

    // Portal-token-authenticated (bearer token is the portal session token)
    Route::group([], function (): void {
        Route::post('/logout', [CustomerPortalController::class, 'logout'])->name('logout');
        Route::get('/profile', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::get('/invoices', [CustomerPortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{id}', [CustomerPortalController::class, 'showInvoice'])->name('invoices.show');
        Route::get('/orders', [CustomerPortalController::class, 'orders'])->name('orders');
        Route::get('/quotations', [CustomerPortalController::class, 'quotations'])->name('quotations');
        Route::get('/statement', [CustomerPortalController::class, 'statement'])->name('statement');

        // --- Extended portal endpoints (Gap 4) ---
        Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices-list', [CustomerPortalController::class, 'invoicesPaginated'])->name('invoices.list');
        Route::get('/invoices/{invoice}/detail', [CustomerPortalController::class, 'invoiceDetail'])->name('invoices.detail');
        Route::get('/orders-list', [CustomerPortalController::class, 'ordersPaginated'])->name('orders.list');
        Route::get('/orders/{salesOrder}/detail', [CustomerPortalController::class, 'orderDetail'])->name('orders.detail');
        Route::get('/quotations-list', [CustomerPortalController::class, 'quotationsPaginated'])->name('quotations.list');
        Route::post('/quotations/{quotation}/accept', [CustomerPortalController::class, 'acceptQuotation'])->name('quotations.accept');
        Route::post('/quotations/{quotation}/decline', [CustomerPortalController::class, 'declineQuotation'])->name('quotations.decline');
        Route::get('/payments', [CustomerPortalController::class, 'payments'])->name('payments');
        Route::get('/outstanding-balance', [CustomerPortalController::class, 'outstandingBalance'])->name('outstanding-balance');
    });
});
