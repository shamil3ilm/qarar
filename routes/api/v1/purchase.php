<?php

use App\Http\Controllers\Api\V1\Purchase\BillController;
use App\Http\Controllers\Api\V1\Purchase\VendorEvaluationController;
use App\Http\Controllers\Api\V1\Purchase\ErsController;
use App\Http\Controllers\Api\V1\Purchase\OutlineAgreementController;
use App\Http\Controllers\Api\V1\Purchase\PaymentMadeController;
use App\Http\Controllers\Api\V1\Purchase\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Purchase\PurchasingInfoRecordController;
use App\Http\Controllers\Api\V1\Purchase\QuotaArrangementController;
use App\Http\Controllers\Api\V1\Purchase\ReleaseStrategyController;
use App\Http\Controllers\Api\V1\Purchase\SchedulingAgreementController;
use App\Http\Controllers\Api\V1\Purchase\ServiceEntrySheetController;
use App\Http\Controllers\Api\V1\Purchase\ThreeWayMatchController;
use App\Http\Controllers\Api\V1\Purchase\VendorConsignmentController;
use App\Http\Controllers\Api\V1\Purchase\VendorContractController;
use App\Http\Controllers\Api\V1\Purchase\VendorCreditNoteController;
use App\Http\Controllers\Api\V1\Purchase\VendorPricingController;
use App\Http\Controllers\Api\V1\Purchase\VendorSourceListController;
use App\Http\Controllers\Api\V1\Purchase\WbsCommitmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Purchase API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/purchase
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('check.permission:purchase.orders.view')->name('purchase.orders.index');
        Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('check.permission:purchase.orders.create')->name('purchase.orders.store');
        Route::get('/summary', [PurchaseOrderController::class, 'summary'])->middleware('check.permission:purchase.orders.view')->name('purchase.orders.summary');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('check.permission:purchase.orders.view')->name('purchase.orders.show');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('check.permission:purchase.orders.edit')->name('purchase.orders.update');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('check.permission:purchase.orders.delete')->name('purchase.orders.destroy');
        Route::post('/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('check.permission:purchase.orders.send')->name('purchase.orders.send');
        Route::post('/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->middleware('check.permission:purchase.orders.confirm')->name('purchase.orders.confirm');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('check.permission:purchase.orders.cancel')->name('purchase.orders.cancel');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('check.permission:purchase.orders.receive')->name('purchase.orders.receive');
        Route::post('/{purchaseOrder}/duplicate', [PurchaseOrderController::class, 'duplicate'])->middleware('check.permission:purchase.orders.create')->name('purchase.orders.duplicate');
        Route::post('/{purchaseOrder}/review-approval', [PurchaseOrderController::class, 'reviewApproval'])->middleware('check.permission:purchase.orders.approve')->name('purchase.orders.review-approval');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Pricing Records
    | Negotiated price, lead time, and order terms per vendor–product pair.
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-pricing')->name('purchase.vendor-pricing.')->group(function () {
        Route::get('/', [VendorPricingController::class, 'index'])->middleware('check.permission:purchase.pir.view')->name('index');
        Route::post('/', [VendorPricingController::class, 'store'])->middleware('check.permission:purchase.pir.create')->name('store');
        Route::get('/for-product/{productId}', [VendorPricingController::class, 'forProduct'])->middleware('check.permission:purchase.pir.view')->name('for-product');
        Route::get('/{id}', [VendorPricingController::class, 'show'])->middleware('check.permission:purchase.pir.view')->name('show');
        Route::put('/{id}', [VendorPricingController::class, 'update'])->middleware('check.permission:purchase.pir.edit')->name('update');
        Route::delete('/{id}', [VendorPricingController::class, 'destroy'])->middleware('check.permission:purchase.pir.delete')->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Source Lists
    | Ordered list of approved vendors per product with priority and quota.
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-source-lists')->name('purchase.vendor-source-lists.')->group(function () {
        Route::get('/', [VendorSourceListController::class, 'index'])->middleware('check.permission:purchase.source-lists.view')->name('index');
        Route::post('/', [VendorSourceListController::class, 'store'])->middleware('check.permission:purchase.source-lists.create')->name('store');
        Route::get('/vendors-for-product/{productId}', [VendorSourceListController::class, 'vendorsForProduct'])->middleware('check.permission:purchase.source-lists.view')->name('vendors-for-product');
        Route::get('/{id}', [VendorSourceListController::class, 'show'])->middleware('check.permission:purchase.source-lists.view')->name('show');
        Route::put('/{id}', [VendorSourceListController::class, 'update'])->middleware('check.permission:purchase.source-lists.edit')->name('update');
        Route::delete('/{id}', [VendorSourceListController::class, 'destroy'])->middleware('check.permission:purchase.source-lists.delete')->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Bills (Supplier Invoices)
    |--------------------------------------------------------------------------
    */
    Route::prefix('bills')->group(function () {
        Route::get('/', [BillController::class, 'index'])->middleware('check.permission:purchase.bills.view')->name('purchase.bills.index');
        Route::post('/', [BillController::class, 'store'])->middleware('check.permission:purchase.bills.create')->name('purchase.bills.store');
        Route::get('/summary', [BillController::class, 'summary'])->middleware('check.permission:purchase.bills.view')->name('purchase.bills.summary');
        Route::post('/from-purchase-order', [BillController::class, 'createFromPurchaseOrder'])->middleware('check.permission:purchase.bills.create')->name('purchase.bills.from-po');
        Route::get('/{bill}', [BillController::class, 'show'])->middleware('check.permission:purchase.bills.view')->name('purchase.bills.show');
        Route::put('/{bill}', [BillController::class, 'update'])->middleware('check.permission:purchase.bills.edit')->name('purchase.bills.update');
        Route::delete('/{bill}', [BillController::class, 'destroy'])->middleware('check.permission:purchase.bills.delete')->name('purchase.bills.destroy');
        Route::post('/{bill}/approve', [BillController::class, 'approve'])->middleware('check.permission:purchase.bills.approve')->name('purchase.bills.approve');
        Route::post('/{bill}/void', [BillController::class, 'void'])->middleware('check.permission:purchase.bills.void')->name('purchase.bills.void');
    });

    /*
    |--------------------------------------------------------------------------
    | Payments Made
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments-made')->group(function () {
        Route::get('/', [PaymentMadeController::class, 'index'])->middleware('check.permission:purchase.payments.view')->name('purchase.payments.index');
        Route::post('/', [PaymentMadeController::class, 'store'])->middleware('check.permission:purchase.payments.create')->name('purchase.payments.store');
        Route::get('/summary', [PaymentMadeController::class, 'summary'])->middleware('check.permission:purchase.payments.view')->name('purchase.payments.summary');
        Route::get('/supplier-statement', [PaymentMadeController::class, 'supplierStatement'])->middleware('check.permission:purchase.payments.view')->name('purchase.payments.supplier-statement');
        Route::get('/{paymentMade}', [PaymentMadeController::class, 'show'])->middleware('check.permission:purchase.payments.view')->name('purchase.payments.show');
        Route::delete('/{paymentMade}', [PaymentMadeController::class, 'destroy'])->middleware('check.permission:purchase.payments.delete')->name('purchase.payments.destroy');
        Route::post('/{paymentMade}/complete', [PaymentMadeController::class, 'complete'])->middleware('check.permission:purchase.payments.complete')->name('purchase.payments.complete');
        Route::post('/{paymentMade}/void', [PaymentMadeController::class, 'void'])->middleware('check.permission:purchase.payments.void')->name('purchase.payments.void');
        Route::post('/{paymentMade}/allocate', [PaymentMadeController::class, 'allocate'])->middleware('check.permission:purchase.payments.allocate')->name('purchase.payments.allocate');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Credit Notes (AP Credit Memos)
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-credit-notes')->group(function () {
        Route::get('/', [VendorCreditNoteController::class, 'index'])
            ->middleware('check.permission:purchase.vendor-credit-notes.view')
            ->name('purchase.vendor-credit-notes.index');
        Route::post('/', [VendorCreditNoteController::class, 'store'])
            ->middleware('check.permission:purchase.vendor-credit-notes.create')
            ->name('purchase.vendor-credit-notes.store');
        Route::get('/{vendorCreditNote}', [VendorCreditNoteController::class, 'show'])
            ->middleware('check.permission:purchase.vendor-credit-notes.view')
            ->name('purchase.vendor-credit-notes.show');
        Route::put('/{vendorCreditNote}', [VendorCreditNoteController::class, 'update'])
            ->middleware('check.permission:purchase.vendor-credit-notes.edit')
            ->name('purchase.vendor-credit-notes.update');
        Route::delete('/{vendorCreditNote}', [VendorCreditNoteController::class, 'destroy'])
            ->middleware('check.permission:purchase.vendor-credit-notes.delete')
            ->name('purchase.vendor-credit-notes.destroy');
        Route::post('/{vendorCreditNote}/post', [VendorCreditNoteController::class, 'post'])
            ->middleware('check.permission:purchase.vendor-credit-notes.post')
            ->name('purchase.vendor-credit-notes.post');
        Route::post('/{vendorCreditNote}/apply', [VendorCreditNoteController::class, 'apply'])
            ->middleware('check.permission:purchase.vendor-credit-notes.apply')
            ->name('purchase.vendor-credit-notes.apply');
        Route::post('/{vendorCreditNote}/void', [VendorCreditNoteController::class, 'void'])
            ->middleware('check.permission:purchase.vendor-credit-notes.void')
            ->name('purchase.vendor-credit-notes.void');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Consignment
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-consignment')->name('purchase.vendor-consignment.')->group(function () {
        Route::get('/stocks', [VendorConsignmentController::class, 'stockIndex'])->middleware('check.permission:purchase.consignment.view')->name('stocks');
        Route::get('/stocks/{id}', [VendorConsignmentController::class, 'stockShow'])->middleware('check.permission:purchase.consignment.view')->name('stocks.show');
        Route::post('/receive', [VendorConsignmentController::class, 'receive'])->middleware('check.permission:purchase.consignment.receive')->name('receive');
        Route::post('/withdraw', [VendorConsignmentController::class, 'withdraw'])->middleware('check.permission:purchase.consignment.withdraw')->name('withdraw');
        Route::get('/settlements', [VendorConsignmentController::class, 'settlements'])->middleware('check.permission:purchase.consignment.view')->name('settlements');
        Route::post('/settlements', [VendorConsignmentController::class, 'createSettlement'])->middleware('check.permission:purchase.consignment.settle')->name('settlements.create');
        Route::post('/settlements/{id}/submit', [VendorConsignmentController::class, 'submitSettlement'])->middleware('check.permission:purchase.consignment.settle')->name('settlements.submit');
    });

    /*
    |--------------------------------------------------------------------------
    | WBS Commitments
    |--------------------------------------------------------------------------
    */
    Route::prefix('wbs-commitments')->name('purchase.wbs-commitments.')->group(function () {
        Route::get('/wbs/{wbsElementId}', [WbsCommitmentController::class, 'forWbs'])->middleware('check.permission:purchase.wbs-commitments.view')->name('for-wbs');
        Route::get('/wbs/{wbsElementId}/budget', [WbsCommitmentController::class, 'budgetVsCommitment'])->middleware('check.permission:purchase.wbs-commitments.view')->name('budget');
        Route::post('/{id}/close', [WbsCommitmentController::class, 'close'])->middleware('check.permission:purchase.wbs-commitments.manage')->name('close');
    });

    /*
    |--------------------------------------------------------------------------
    | Purchasing Info Records (MM)
    | SAP-style vendor-material pricing records with validity-period conditions.
    |--------------------------------------------------------------------------
    */
    Route::prefix('info-records')->name('purchase.info-records.')->group(function () {
        Route::get('/', [PurchasingInfoRecordController::class, 'index'])->middleware('check.permission:purchase.info-records.view')->name('index');
        Route::post('/', [PurchasingInfoRecordController::class, 'store'])->middleware('check.permission:purchase.info-records.create')->name('store');
        Route::get('/price-for', [PurchasingInfoRecordController::class, 'priceFor'])->middleware('check.permission:purchase.info-records.view')->name('price-for');
        Route::get('/{id}', [PurchasingInfoRecordController::class, 'show'])->middleware('check.permission:purchase.info-records.view')->name('show');
        Route::put('/{id}', [PurchasingInfoRecordController::class, 'update'])->middleware('check.permission:purchase.info-records.edit')->name('update');
        Route::delete('/{id}', [PurchasingInfoRecordController::class, 'destroy'])->middleware('check.permission:purchase.info-records.delete')->name('destroy');
        Route::post('/{id}/deactivate', [PurchasingInfoRecordController::class, 'deactivate'])->middleware('check.permission:purchase.info-records.edit')->name('deactivate');
        Route::post('/{id}/conditions', [PurchasingInfoRecordController::class, 'addCondition'])->middleware('check.permission:purchase.info-records.edit')->name('conditions.add');
        Route::put('/{id}/conditions/{conditionId}', [PurchasingInfoRecordController::class, 'updateCondition'])->middleware('check.permission:purchase.info-records.edit')->name('conditions.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Quota Arrangements (MM)
    | Splits procurement of a material across multiple vendors by quota %.
    |--------------------------------------------------------------------------
    */
    Route::prefix('quota-arrangements')->name('purchase.quota-arrangements.')->group(function () {
        Route::get('/', [QuotaArrangementController::class, 'index'])->middleware('check.permission:purchase.quota-arrangements.view')->name('index');
        Route::post('/', [QuotaArrangementController::class, 'store'])->middleware('check.permission:purchase.quota-arrangements.create')->name('store');
        Route::post('/determine-source', [QuotaArrangementController::class, 'determineSource'])->middleware('check.permission:purchase.quota-arrangements.view')->name('determine-source');
        Route::get('/{id}', [QuotaArrangementController::class, 'show'])->middleware('check.permission:purchase.quota-arrangements.view')->name('show');
        Route::put('/{id}', [QuotaArrangementController::class, 'update'])->middleware('check.permission:purchase.quota-arrangements.edit')->name('update');
        Route::delete('/{id}', [QuotaArrangementController::class, 'destroy'])->middleware('check.permission:purchase.quota-arrangements.delete')->name('destroy');
        Route::post('/{id}/items', [QuotaArrangementController::class, 'addItem'])->middleware('check.permission:purchase.quota-arrangements.edit')->name('items.add');
        Route::put('/{id}/items/{itemId}', [QuotaArrangementController::class, 'updateItem'])->middleware('check.permission:purchase.quota-arrangements.edit')->name('items.update');
        Route::delete('/{id}/items/{itemId}', [QuotaArrangementController::class, 'removeItem'])->middleware('check.permission:purchase.quota-arrangements.edit')->name('items.remove');
        Route::post('/{id}/reset-allocations', [QuotaArrangementController::class, 'resetAllocations'])->middleware('check.permission:purchase.quota-arrangements.edit')->name('reset-allocations');
    });

    /*
    |--------------------------------------------------------------------------
    | Vendor Contracts
    |--------------------------------------------------------------------------
    */
    Route::prefix('vendor-contracts')->name('purchase.vendor-contracts.')->group(function () {
        Route::get('/', [VendorContractController::class, 'index'])->middleware('check.permission:purchase.contracts.view')->name('index');
        Route::post('/', [VendorContractController::class, 'store'])->middleware('check.permission:purchase.contracts.create')->name('store');
        Route::get('/expiring', [VendorContractController::class, 'expiring'])->middleware('check.permission:purchase.contracts.view')->name('expiring');
        Route::get('/{id}', [VendorContractController::class, 'show'])->middleware('check.permission:purchase.contracts.view')->name('show');
        Route::post('/{id}/activate', [VendorContractController::class, 'activate'])->middleware('check.permission:purchase.contracts.manage')->name('activate');
        Route::post('/{id}/terminate', [VendorContractController::class, 'terminate'])->middleware('check.permission:purchase.contracts.manage')->name('terminate');
    });

    /*
    |--------------------------------------------------------------------------
    | Outline Agreements (MM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('outline-agreements')->name('mm.outline-agreements.')->group(function () {
        Route::get('/', [OutlineAgreementController::class, 'index'])->name('index');
        Route::post('/', [OutlineAgreementController::class, 'store'])->name('store');
        Route::get('/{id}', [OutlineAgreementController::class, 'show'])->name('show');
        Route::put('/{id}', [OutlineAgreementController::class, 'update'])->name('update');
        Route::delete('/{id}', [OutlineAgreementController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/items', [OutlineAgreementController::class, 'addItem'])->name('items.add');
        Route::put('/{id}/items/{itemId}', [OutlineAgreementController::class, 'updateItem'])->name('items.update');
        Route::post('/{id}/releases', [OutlineAgreementController::class, 'createRelease'])->name('releases.create');
        Route::get('/{id}/releases', [OutlineAgreementController::class, 'getReleases'])->name('releases.index');
        Route::post('/{id}/activate', [OutlineAgreementController::class, 'activate'])->name('activate');
        Route::post('/{id}/cancel', [OutlineAgreementController::class, 'cancel'])->name('cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Scheduling Agreements (MM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('scheduling-agreements')->name('mm.scheduling-agreements.')->group(function () {
        Route::get('/', [SchedulingAgreementController::class, 'index'])->name('index');
        Route::post('/', [SchedulingAgreementController::class, 'store'])->name('store');
        Route::get('/{id}', [SchedulingAgreementController::class, 'show'])->name('show');
        Route::put('/{id}', [SchedulingAgreementController::class, 'update'])->name('update');
        Route::delete('/{id}', [SchedulingAgreementController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/schedule', [SchedulingAgreementController::class, 'addSchedule'])->name('schedule.add');
        Route::put('/{id}/schedule/{lineId}', [SchedulingAgreementController::class, 'updateSchedule'])->name('schedule.update');
        Route::post('/{id}/schedule/{lineId}/receive', [SchedulingAgreementController::class, 'receiveDelivery'])->name('receive');
        Route::get('/{id}/schedules', [SchedulingAgreementController::class, 'getSchedules'])->name('schedules');
    });

    /*
    |--------------------------------------------------------------------------
    | Evaluated Receipt Settlement (MM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('ers')->name('mm.ers.')->group(function () {
        Route::get('/configs', [ErsController::class, 'configs'])->name('configs');
        Route::post('/configs', [ErsController::class, 'saveConfig'])->name('configs.save');
        Route::post('/run', [ErsController::class, 'runErs'])->name('run');
        Route::get('/runs', [ErsController::class, 'getRuns'])->name('runs');
        Route::get('/runs/{runId}/items', [ErsController::class, 'getRunItems'])->name('run-items');
    });

    /*
    |--------------------------------------------------------------------------
    | Release Strategies (SAP ME28/ME29 — multi-level PO/PR approval)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('release-strategies', ReleaseStrategyController::class)
        ->names('purchase.release-strategies');
    Route::post('release-strategies/{releaseStrategy}/levels', [ReleaseStrategyController::class, 'addLevel'])
        ->name('purchase.release-strategies.levels.add');
    Route::delete('release-strategies/{releaseStrategy}/levels/{level}', [ReleaseStrategyController::class, 'removeLevel'])
        ->name('purchase.release-strategies.levels.remove');
    Route::get('release-approval-status', [ReleaseStrategyController::class, 'approvalStatus'])
        ->name('purchase.release-strategies.status');
    Route::post('release-approvals/{approval}/approve', [ReleaseStrategyController::class, 'approve'])
        ->name('purchase.release-approvals.approve');
    Route::post('release-approvals/{approval}/reject', [ReleaseStrategyController::class, 'reject'])
        ->name('purchase.release-approvals.reject');

    /*
    |--------------------------------------------------------------------------
    | Service Entry Sheets (SAP ML81N / SES)
    | Records services actually rendered against a service purchase order line.
    |--------------------------------------------------------------------------
    */
    Route::prefix('service-entry-sheets')->name('purchase.ses.')->group(function () {
        Route::get('/', [ServiceEntrySheetController::class, 'index'])->middleware('check.permission:purchase.ses.view')->name('index');
        Route::post('/', [ServiceEntrySheetController::class, 'store'])->middleware('check.permission:purchase.ses.create')->name('store');
        Route::get('{uuid}', [ServiceEntrySheetController::class, 'show'])->middleware('check.permission:purchase.ses.view')->name('show');
        Route::put('{uuid}', [ServiceEntrySheetController::class, 'update'])->middleware('check.permission:purchase.ses.edit')->name('update');
        Route::post('{uuid}/submit', [ServiceEntrySheetController::class, 'submit'])->middleware('check.permission:purchase.ses.submit')->name('submit');
        Route::post('{uuid}/review', [ServiceEntrySheetController::class, 'review'])->middleware('check.permission:purchase.ses.approve')->name('review');
    });

    /*
    |--------------------------------------------------------------------------
    | Three-Way Match (SAP MIRO / MR8M)
    | PO / GR / Invoice matching results and exception reporting.
    |--------------------------------------------------------------------------
    */
    Route::prefix('three-way-match')->name('purchase.three-way-match.')->group(function () {
        Route::get('/', [ThreeWayMatchController::class, 'index'])->middleware('check.permission:purchase.bills.view')->name('index');
        Route::get('/exceptions', [ThreeWayMatchController::class, 'exceptions'])->middleware('check.permission:purchase.bills.view')->name('exceptions');
    });
});

/*
|--------------------------------------------------------------------------
| Vendor / Supplier Evaluation — SAP MM ME61
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('vendor-evaluation')->name('purchase.vendor-eval.')->group(function (): void {
    // Criteria
    Route::get('/criteria', [VendorEvaluationController::class, 'criteria'])->name('criteria.index');
    Route::post('/criteria', [VendorEvaluationController::class, 'storeCriterion'])->name('criteria.store');

    // Scorecards
    Route::get('/scorecards', [VendorEvaluationController::class, 'index'])->name('scorecards.index');
    Route::post('/scorecards', [VendorEvaluationController::class, 'store'])->name('scorecards.store');
    Route::get('/scorecards/{supplierScorecard}', [VendorEvaluationController::class, 'show'])->name('scorecards.show');
    Route::put('/scorecards/{supplierScorecard}/ratings', [VendorEvaluationController::class, 'updateRatings'])->name('scorecards.ratings');
    Route::post('/scorecards/{supplierScorecard}/finalize', [VendorEvaluationController::class, 'finalize'])->name('scorecards.finalize');

    // Reporting
    Route::get('/ranking', [VendorEvaluationController::class, 'ranking'])->name('ranking');
    Route::get('/suppliers/{supplierId}/trend', [VendorEvaluationController::class, 'trend'])->name('supplier-trend');
});
