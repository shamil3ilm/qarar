<?php

use App\Http\Controllers\Api\V1\Accounting\AccountController;
use App\Http\Controllers\Api\V1\Accounting\FxDerivativeController;
use App\Http\Controllers\Api\V1\Accounting\IntercompanyReconciliationController;
use App\Http\Controllers\Api\V1\Accounting\AccountGroupController;
use App\Http\Controllers\Api\V1\Accounting\AssetComponentController;
use App\Http\Controllers\Api\V1\Accounting\AssetTransferController;
use App\Http\Controllers\Api\V1\Accounting\HouseBankController;
use App\Http\Controllers\Api\V1\Accounting\InstallmentPlanController;
use App\Http\Controllers\Api\V1\Accounting\PaymentToleranceController;
use App\Http\Controllers\Api\V1\Accounting\WithholdingTaxController;
use App\Http\Controllers\Api\V1\Accounting\CoReconciliationController;
use App\Http\Controllers\Api\V1\Accounting\ParallelLedgerController;
use App\Http\Controllers\Api\V1\Accounting\EbamController;
use App\Http\Controllers\Api\V1\Accounting\LeaseAccountingController;
use App\Http\Controllers\Api\V1\Accounting\XbrlController;
use App\Http\Controllers\Api\V1\Accounting\AgingReportController;
use App\Http\Controllers\Api\V1\Accounting\BankGuaranteeController;
use App\Http\Controllers\Api\V1\Accounting\CashDiscountController;
use App\Http\Controllers\Api\V1\Accounting\CheckManagementController;
use App\Http\Controllers\Api\V1\Accounting\DocumentTypeController;
use App\Http\Controllers\Api\V1\Accounting\GrIrClearingController;
use App\Http\Controllers\Api\V1\Accounting\CostingSheetController;
use App\Http\Controllers\Api\V1\Accounting\CostSplittingController;
use App\Http\Controllers\Api\V1\Accounting\DirectDebitController;
use App\Http\Controllers\Api\V1\Accounting\FinancialCloseCockpitController;
use App\Http\Controllers\Api\V1\Accounting\FinancialStatementVersionController;
use App\Http\Controllers\Api\V1\Accounting\FiscalYearController;
use App\Http\Controllers\Api\V1\Accounting\JournalEntryController;
use App\Http\Controllers\Api\V1\Accounting\OpenItemClearingController;
use App\Http\Controllers\Api\V1\Accounting\OverheadKeyController;
use App\Http\Controllers\Api\V1\Accounting\PaymentFileController;
use App\Http\Controllers\Api\V1\Accounting\ProfitabilitySegmentController;
use App\Http\Controllers\Api\V1\Accounting\RecurringJournalController;
use App\Http\Controllers\Api\V1\Accounting\ReportController;
use App\Http\Controllers\Api\V1\Accounting\SpecialLedgerController;
use App\Http\Controllers\Api\V1\Accounting\StatisticalKeyFigureController;
use App\Http\Controllers\Api\V1\Accounting\TransferPricingController;
use App\Http\Controllers\Api\V1\Accounting\VarianceAnalysisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting Module Routes
|--------------------------------------------------------------------------
*/

// Chart of Accounts
Route::prefix('accounts')->group(function () {
    Route::get('/', [AccountController::class, 'index'])
        ->middleware('check.permission:accounting.accounts.view')
        ->name('accounts.index');

    Route::get('/flat', [AccountController::class, 'flat'])
        ->middleware('check.permission:accounting.accounts.view')
        ->name('accounts.flat');

    Route::post('/', [AccountController::class, 'store'])
        ->middleware('check.permission:accounting.accounts.create')
        ->name('accounts.store');

    Route::get('/{account}', [AccountController::class, 'show'])
        ->middleware('check.permission:accounting.accounts.view')
        ->name('accounts.show');

    Route::put('/{account}', [AccountController::class, 'update'])
        ->middleware('check.permission:accounting.accounts.update')
        ->name('accounts.update');

    Route::delete('/{account}', [AccountController::class, 'destroy'])
        ->middleware('check.permission:accounting.accounts.delete')
        ->name('accounts.destroy');

    Route::get('/{account}/ledger', [AccountController::class, 'ledger'])
        ->middleware('check.permission:accounting.accounts.view')
        ->name('accounts.ledger');
});

// Fiscal Years
Route::prefix('fiscal-years')->group(function () {
    Route::get('/', [FiscalYearController::class, 'index'])
        ->middleware('check.permission:accounting.fiscal-years.view')
        ->name('fiscal-years.index');

    Route::get('/current', [FiscalYearController::class, 'current'])
        ->middleware('check.permission:accounting.fiscal-years.view')
        ->name('fiscal-years.current');

    Route::post('/', [FiscalYearController::class, 'store'])
        ->middleware('check.permission:accounting.fiscal-years.create')
        ->name('fiscal-years.store');

    Route::get('/{fiscalYear}', [FiscalYearController::class, 'show'])
        ->middleware('check.permission:accounting.fiscal-years.view')
        ->name('fiscal-years.show');

    Route::put('/{fiscalYear}', [FiscalYearController::class, 'update'])
        ->middleware('check.permission:accounting.fiscal-years.update')
        ->name('fiscal-years.update');

    Route::post('/{fiscalYear}/set-current', [FiscalYearController::class, 'setCurrent'])
        ->middleware('check.permission:accounting.fiscal-years.update')
        ->name('fiscal-years.set-current');

    Route::post('/{fiscalYear}/close', [FiscalYearController::class, 'close'])
        ->middleware(['check.permission:accounting.fiscal-years.close', 'throttle:api-financial', 'simulation'])
        ->name('fiscal-years.close');

    Route::delete('/{fiscalYear}', [FiscalYearController::class, 'destroy'])
        ->middleware('check.permission:accounting.fiscal-years.delete')
        ->name('fiscal-years.destroy');

    Route::post('/initialize-coa', [FiscalYearController::class, 'initializeChartOfAccounts'])
        ->middleware('check.permission:accounting.accounts.create')
        ->name('fiscal-years.initialize-coa');
});

// Journal Entries
Route::prefix('journal-entries')->group(function () {
    Route::get('/', [JournalEntryController::class, 'index'])
        ->middleware('check.permission:accounting.journals.view')
        ->name('journal-entries.index');

    Route::post('/', [JournalEntryController::class, 'store'])
        ->middleware('check.permission:accounting.journals.create')
        ->name('journal-entries.store');

    Route::get('/{journalEntry}', [JournalEntryController::class, 'show'])
        ->middleware('check.permission:accounting.journals.view')
        ->name('journal-entries.show');

    Route::put('/{journalEntry}', [JournalEntryController::class, 'update'])
        ->middleware('check.permission:accounting.journals.update')
        ->name('journal-entries.update');

    Route::delete('/{journalEntry}', [JournalEntryController::class, 'destroy'])
        ->middleware('check.permission:accounting.journals.delete')
        ->name('journal-entries.destroy');

    Route::post('/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->middleware(['check.permission:accounting.journals.post', 'throttle:api-financial'])
        ->name('journal-entries.post');

    Route::post('/{journalEntry}/void', [JournalEntryController::class, 'void'])
        ->middleware(['check.permission:accounting.journals.void', 'throttle:api-financial'])
        ->name('journal-entries.void');

    Route::post('/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
        ->middleware(['check.permission:accounting.journals.reverse', 'throttle:api-financial'])
        ->name('journal-entries.reverse');
});

// Reports
Route::prefix('reports')->middleware('check.permission:accounting.reports.view')->group(function () {
    Route::get('/trial-balance', [ReportController::class, 'trialBalance'])
        ->name('reports.trial-balance');

    Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])
        ->name('reports.balance-sheet');

    Route::get('/income-statement', [ReportController::class, 'incomeStatement'])
        ->name('reports.income-statement');
});

// Recurring Journal Templates (FBD1/FBD3)
Route::middleware(['auth:api'])->group(function () {
    Route::post(
        'recurring-journal-templates/run-due',
        [RecurringJournalController::class, 'runDue']
    )->name('accounting.recurring-journals.run-due');

    Route::post(
        'recurring-journal-templates/{recurringJournalTemplate}/execute',
        [RecurringJournalController::class, 'execute']
    )->name('accounting.recurring-journals.execute');

    Route::apiResource('recurring-journal-templates', RecurringJournalController::class)
        ->names('accounting.recurring-journals');
});

// Financial Statement Versions (FSV)
Route::middleware(['auth:api'])->group(function () {
    Route::apiResource('financial-statement-versions', FinancialStatementVersionController::class)
        ->names('accounting.fsv');

    Route::post(
        'financial-statement-versions/{financialStatementVersion}/nodes',
        [FinancialStatementVersionController::class, 'addNode']
    )->name('accounting.fsv.nodes.add');

    Route::delete(
        'financial-statement-versions/{financialStatementVersion}/nodes/{node}',
        [FinancialStatementVersionController::class, 'removeNode']
    )->name('accounting.fsv.nodes.remove');

    Route::get(
        'financial-statement-versions/{financialStatementVersion}/generate',
        [FinancialStatementVersionController::class, 'generate']
    )->name('accounting.fsv.generate');
});

// Special Purpose Ledger (FI-SL)
Route::middleware(['auth:api'])->prefix('special-ledgers')->name('accounting.special-ledgers.')->group(function () {
    Route::get('/', [SpecialLedgerController::class, 'index'])->name('index');
    Route::post('/', [SpecialLedgerController::class, 'store'])->name('store');
    Route::get('/{id}', [SpecialLedgerController::class, 'show'])->name('show');
    Route::put('/{id}', [SpecialLedgerController::class, 'update'])->name('update');
    Route::delete('/{id}', [SpecialLedgerController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/trial-balance', [SpecialLedgerController::class, 'trialBalance'])->name('trial-balance');
    Route::get('/{id}/entries', [SpecialLedgerController::class, 'entries'])->name('entries');
});

// Payment Files — SEPA / ISO 20022 (FI-BL)
Route::middleware(['auth:api'])->prefix('payment-files')->name('accounting.payment-files.')->group(function () {
    Route::get('/', [PaymentFileController::class, 'index'])->name('index');
    Route::get('/{id}', [PaymentFileController::class, 'show'])->name('show');
    Route::post('/generate', [PaymentFileController::class, 'generate'])->name('generate');
    Route::get('/{id}/download', [PaymentFileController::class, 'download'])->name('download');
    Route::post('/{id}/submit', [PaymentFileController::class, 'submit'])->name('submit');
    Route::post('/{id}/acknowledge', [PaymentFileController::class, 'acknowledge'])->name('acknowledge');
});

// Financial Close Cockpit (FIN-FCCM)
Route::middleware(['auth:api'])->prefix('financial-close')->name('accounting.financial-close.')->group(function () {
    Route::get('/templates', [FinancialCloseCockpitController::class, 'templates'])->name('templates');
    Route::post('/templates', [FinancialCloseCockpitController::class, 'storeTemplate'])->name('templates.store');
    Route::get('/periods', [FinancialCloseCockpitController::class, 'periods'])->name('periods');
    Route::post('/periods', [FinancialCloseCockpitController::class, 'storePeriod'])->name('periods.store');
    Route::get('/periods/{id}', [FinancialCloseCockpitController::class, 'showPeriod'])->name('periods.show');
    Route::get('/periods/{id}/progress', [FinancialCloseCockpitController::class, 'progress'])->name('periods.progress');
    Route::post('/periods/{id}/close', [FinancialCloseCockpitController::class, 'closePeriod'])->middleware(['throttle:api-financial', 'simulation'])->name('periods.close');
    Route::post('/tasks/{taskId}/start', [FinancialCloseCockpitController::class, 'startTask'])->name('tasks.start');
    Route::post('/tasks/{taskId}/complete', [FinancialCloseCockpitController::class, 'completeTask'])->name('tasks.complete');
    Route::post('/tasks/{taskId}/skip', [FinancialCloseCockpitController::class, 'skipTask'])->name('tasks.skip');
    Route::put('/tasks/{taskId}/assign', [FinancialCloseCockpitController::class, 'assignTask'])->name('tasks.assign');
    Route::post('/periods/{id}/sign-off', [FinancialCloseCockpitController::class, 'signOff'])->middleware('throttle:api-financial')->name('periods.sign-off');
});

// Transfer Pricing (CO-PC-TPC)
Route::middleware(['auth:api'])->prefix('transfer-pricing')->name('accounting.transfer-pricing.')->group(function () {
    Route::get('/', [TransferPricingController::class, 'index'])->name('index');
    Route::post('/', [TransferPricingController::class, 'store'])->name('store');
    Route::get('/versions', [TransferPricingController::class, 'versions'])->name('versions');
    Route::post('/versions', [TransferPricingController::class, 'storeVersion'])->name('versions.store');
    Route::post('/versions/{id}/activate', [TransferPricingController::class, 'activateVersion'])->name('versions.activate');
    Route::post('/calculate', [TransferPricingController::class, 'calculate'])->name('calculate');
    Route::get('/{id}', [TransferPricingController::class, 'show'])->name('show');
    Route::put('/{id}', [TransferPricingController::class, 'update'])->name('update');
    Route::delete('/{id}', [TransferPricingController::class, 'destroy'])->name('destroy');
});

// Costing Sheets (CO-PC-OVH)
Route::middleware(['auth:api'])->prefix('costing-sheets')->name('accounting.costing-sheets.')->group(function () {
    Route::get('/', [CostingSheetController::class, 'index'])->name('index');
    Route::post('/', [CostingSheetController::class, 'store'])->name('store');
    Route::get('/{id}', [CostingSheetController::class, 'show'])->name('show');
    Route::put('/{id}', [CostingSheetController::class, 'update'])->name('update');
    Route::delete('/{id}', [CostingSheetController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/rows', [CostingSheetController::class, 'rows'])->name('rows');
    Route::post('/{id}/rows', [CostingSheetController::class, 'addRow'])->name('rows.add');
    Route::post('/{id}/run', [CostingSheetController::class, 'run'])->name('run');
});

// Overhead Keys (CO-PC-OVH)
Route::middleware(['auth:api'])->prefix('overhead-keys')->name('accounting.overhead-keys.')->group(function () {
    Route::get('/', [OverheadKeyController::class, 'index'])->name('index');
    Route::post('/', [OverheadKeyController::class, 'store'])->name('store');
    Route::get('/{id}', [OverheadKeyController::class, 'show'])->name('show');
    Route::put('/{id}', [OverheadKeyController::class, 'update'])->name('update');
    Route::delete('/{id}', [OverheadKeyController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/rates', [OverheadKeyController::class, 'rates'])->name('rates');
    Route::post('/{id}/rates', [OverheadKeyController::class, 'addRate'])->name('rates.add');
});

// Statistical Key Figures (CO-OM-SKF)
Route::middleware(['auth:api'])->prefix('statistical-key-figures')->name('co.skf.')->group(function () {
    Route::get('/period-values', [StatisticalKeyFigureController::class, 'periodValues'])->name('period-values');
    Route::get('/', [StatisticalKeyFigureController::class, 'index'])->name('index');
    Route::post('/', [StatisticalKeyFigureController::class, 'store'])->name('store');
    Route::get('/{id}', [StatisticalKeyFigureController::class, 'show'])->name('show');
    Route::put('/{id}', [StatisticalKeyFigureController::class, 'update'])->name('update');
    Route::delete('/{id}', [StatisticalKeyFigureController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/post-value', [StatisticalKeyFigureController::class, 'postValue'])->name('post-value');
});

// Cost Splitting (CO-OM-CEL)
Route::middleware(['auth:api'])->prefix('cost-splitting')->name('co.cost-splitting.')->group(function () {
    Route::get('/results', [CostSplittingController::class, 'results'])->name('results');
    Route::post('/run', [CostSplittingController::class, 'runSplitting'])->name('run');
    Route::get('/rules', [CostSplittingController::class, 'index'])->name('index');
    Route::post('/rules', [CostSplittingController::class, 'store'])->name('store');
    Route::get('/rules/{id}', [CostSplittingController::class, 'show'])->name('show');
    Route::put('/rules/{id}', [CostSplittingController::class, 'update'])->name('update');
    Route::delete('/rules/{id}', [CostSplittingController::class, 'destroy'])->name('destroy');
});

// Variance Analysis (CO-PC-ACT)
Route::middleware(['auth:api'])->prefix('variance-analysis')->name('co.variance.')->group(function () {
    Route::get('/summary', [VarianceAnalysisController::class, 'summary'])->name('summary');
    Route::get('/', [VarianceAnalysisController::class, 'index'])->name('index');
    Route::post('/', [VarianceAnalysisController::class, 'store'])->name('store');
    Route::get('/{id}', [VarianceAnalysisController::class, 'show'])->name('show');
    Route::get('/{id}/results', [VarianceAnalysisController::class, 'results'])->name('results');
});

// Profitability Segments (CO-PA)
Route::middleware(['auth:api'])->prefix('profitability-segments')->name('co.profitability-segments.')->group(function () {
    Route::get('/drill-down', [ProfitabilitySegmentController::class, 'drillDown'])->name('drill-down');
    Route::get('/report', [ProfitabilitySegmentController::class, 'report'])->name('report');
    Route::post('/post-values', [ProfitabilitySegmentController::class, 'postValues'])->name('post-values');
    Route::get('/', [ProfitabilitySegmentController::class, 'index'])->name('index');
    Route::post('/', [ProfitabilitySegmentController::class, 'store'])->name('store');
    Route::get('/{id}', [ProfitabilitySegmentController::class, 'show'])->name('show');
    Route::put('/{id}', [ProfitabilitySegmentController::class, 'update'])->name('update');
    Route::delete('/{id}', [ProfitabilitySegmentController::class, 'destroy'])->name('destroy');
});

// Bank Guarantees (FI-BG)
Route::prefix('bank-guarantees')->name('fi.bank-guarantees.')->group(function () {
    Route::get('/', [BankGuaranteeController::class, 'index'])->name('index');
    Route::post('/', [BankGuaranteeController::class, 'store'])->name('store');
    Route::get('/expiring-soon', [BankGuaranteeController::class, 'expiringSoon'])->name('expiring-soon');
    Route::get('/{id}', [BankGuaranteeController::class, 'show'])->name('show');
    Route::put('/{id}', [BankGuaranteeController::class, 'update'])->name('update');
    Route::delete('/{id}', [BankGuaranteeController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/activate', [BankGuaranteeController::class, 'activate'])->name('activate');
    Route::post('/{id}/claim', [BankGuaranteeController::class, 'claim'])->name('claim');
    Route::post('/{id}/return', [BankGuaranteeController::class, 'returnGuarantee'])->name('return');
});

// Check Management (FI-BL)
Route::prefix('check-books')->name('fi.check-books.')->group(function () {
    Route::get('/', [CheckManagementController::class, 'listBooks'])->name('index');
    Route::post('/', [CheckManagementController::class, 'createBook'])->name('store');
    Route::put('/{id}', [CheckManagementController::class, 'updateBook'])->name('update');
    Route::delete('/{id}', [CheckManagementController::class, 'destroyBook'])->name('destroy');
});

Route::prefix('checks')->name('fi.checks.')->group(function () {
    Route::get('/', [CheckManagementController::class, 'listChecks'])->name('index');
    Route::post('/', [CheckManagementController::class, 'createCheck'])->name('store');
    Route::get('/outstanding', [CheckManagementController::class, 'outstanding'])->name('outstanding');
    Route::get('/{id}', [CheckManagementController::class, 'showCheck'])->name('show');
    Route::post('/{id}/print', [CheckManagementController::class, 'printCheck'])->name('print');
    Route::post('/{id}/issue', [CheckManagementController::class, 'issue'])->name('issue');
    Route::post('/{id}/clear', [CheckManagementController::class, 'markCleared'])->name('clear');
    Route::post('/{id}/bounce', [CheckManagementController::class, 'markBounced'])->name('bounce');
    Route::post('/{id}/cancel', [CheckManagementController::class, 'cancel'])->name('cancel');
});

// Direct Debit & Standing Orders (FI-BL)
Route::prefix('direct-debit')->name('fi.direct-debit.')->group(function () {
    Route::get('/mandates', [DirectDebitController::class, 'listMandates'])->name('mandates.index');
    Route::post('/mandates', [DirectDebitController::class, 'createMandate'])->name('mandates.store');
    Route::get('/mandates/{id}', [DirectDebitController::class, 'showMandate'])->name('mandates.show');
    Route::put('/mandates/{id}', [DirectDebitController::class, 'updateMandate'])->name('mandates.update');
    Route::post('/mandates/{id}/activate', [DirectDebitController::class, 'activate'])->name('mandates.activate');
    Route::post('/mandates/{id}/pause', [DirectDebitController::class, 'pause'])->name('mandates.pause');
    Route::post('/mandates/{id}/cancel', [DirectDebitController::class, 'cancelMandate'])->name('mandates.cancel');
    Route::get('/mandates/{id}/collections', [DirectDebitController::class, 'collections'])->name('collections');
    Route::get('/due-collections', [DirectDebitController::class, 'dueCollections'])->name('due');
    Route::post('/generate-collections', [DirectDebitController::class, 'generateCollections'])->name('generate');
    Route::post('/collections/{collectionId}/process', [DirectDebitController::class, 'processCollection'])->name('process');
});

// AR/AP Aging Reports (FI-AR/FI-AP)
Route::middleware(['auth:api'])->group(function (): void {
    Route::get('reports/ar-aging', [AgingReportController::class, 'arAging'])
        ->name('accounting.reports.ar-aging');
    Route::get('reports/ap-aging', [AgingReportController::class, 'apAging'])
        ->name('accounting.reports.ap-aging');
});

// Open Item Clearing (FI-AR/FI-AP)
Route::middleware(['auth:api'])->group(function (): void {
    Route::get('open-items/ar', [OpenItemClearingController::class, 'arOpenItems'])
        ->name('accounting.open-items.ar');
    Route::post('open-items/ar/clear', [OpenItemClearingController::class, 'clearAr'])
        ->name('accounting.open-items.ar.clear');
    Route::get('open-items/ap', [OpenItemClearingController::class, 'apOpenItems'])
        ->name('accounting.open-items.ap');
    Route::post('open-items/ap/clear', [OpenItemClearingController::class, 'clearAp'])
        ->name('accounting.open-items.ap.clear');
});

// Cash Discounts / Payment Terms (FI-AR)
Route::middleware(['auth:api'])->group(function (): void {
    Route::get('payment-terms', [CashDiscountController::class, 'indexTerms'])
        ->name('accounting.payment-terms.index');
    Route::post('payment-terms', [CashDiscountController::class, 'storeTerms'])
        ->name('accounting.payment-terms.store');
    Route::post('cash-discounts/preview', [CashDiscountController::class, 'preview'])
        ->name('accounting.cash-discounts.preview');
    Route::post('cash-discounts/apply', [CashDiscountController::class, 'apply'])
        ->name('accounting.cash-discounts.apply');
});

// Document Types (SAP OBA1)
Route::middleware(['auth:api'])->prefix('document-types')->name('accounting.document-types.')->group(function () {
    Route::get('/', [DocumentTypeController::class, 'index'])->name('index');
    Route::post('/', [DocumentTypeController::class, 'store'])->name('store');
    Route::get('/{documentType}', [DocumentTypeController::class, 'show'])->name('show');
    Route::put('/{documentType}', [DocumentTypeController::class, 'update'])->name('update');
    Route::delete('/{documentType}', [DocumentTypeController::class, 'destroy'])->name('destroy');
});

// Account Groups (SAP GL4 / OBD4)
Route::middleware(['auth:api'])->prefix('account-groups')->name('accounting.account-groups.')->group(function () {
    Route::get('/', [AccountGroupController::class, 'index'])->name('index');
    Route::post('/', [AccountGroupController::class, 'store'])->name('store');
    Route::get('/{accountGroup}', [AccountGroupController::class, 'show'])->name('show');
    Route::put('/{accountGroup}', [AccountGroupController::class, 'update'])->name('update');
    Route::delete('/{accountGroup}', [AccountGroupController::class, 'destroy'])->name('destroy');
});

// GR/IR Account Clearing (SAP MR11)
Route::middleware(['auth:api'])->prefix('grir')->name('accounting.grir.')->group(function () {
    Route::get('/open-items', [GrIrClearingController::class, 'index'])->name('open-items');
    Route::post('/clear/{poLineId}', [GrIrClearingController::class, 'clear'])->name('clear');
    Route::get('/report', [GrIrClearingController::class, 'report'])->name('report');
});

// Asset Component Accounting — FI-AA sub-asset tracking (SAP AS02 component accounting)
Route::middleware(['auth:api'])->prefix('assets/{fixedAsset}/components')->name('fi.asset-components.')->group(function () {
    Route::get('/', [AssetComponentController::class, 'index'])
        ->middleware('check.permission:accounting.assets.view')->name('index');
    Route::post('/', [AssetComponentController::class, 'store'])
        ->middleware('check.permission:accounting.assets.create')->name('store');
    Route::get('/{assetComponent}', [AssetComponentController::class, 'show'])
        ->middleware('check.permission:accounting.assets.view')->name('show');
    Route::post('/{assetComponent}/retire', [AssetComponentController::class, 'retire'])
        ->middleware('check.permission:accounting.assets.dispose')->name('retire');
    Route::delete('/{assetComponent}', [AssetComponentController::class, 'destroy'])
        ->middleware('check.permission:accounting.assets.delete')->name('destroy');
});

// eBAM — Electronic Bank Account Management (SAP FI-BL EBAM)
Route::middleware(['auth:api'])->name('fi.ebam.')->group(function () {
    // Signatories per bank account
    Route::get('bank-accounts/{bankAccount}/signatories', [EbamController::class, 'signatories'])
        ->middleware('check.permission:accounting.bank-accounts.view')->name('signatories.index');
    Route::post('bank-accounts/{bankAccount}/signatories', [EbamController::class, 'addSignatory'])
        ->middleware('check.permission:accounting.bank-accounts.manage')->name('signatories.store');
    Route::post('bank-accounts/{bankAccount}/signatories/{bankSignatory}/revoke', [EbamController::class, 'revokeSignatory'])
        ->middleware('check.permission:accounting.bank-accounts.manage')->name('signatories.revoke');

    // Bank account opening/closing/modification requests
    Route::get('bank-account-requests', [EbamController::class, 'requests'])
        ->middleware('check.permission:accounting.bank-accounts.view')->name('requests.index');
    Route::get('bank-account-requests/{bankAccountRequest}', [EbamController::class, 'showRequest'])
        ->middleware('check.permission:accounting.bank-accounts.view')->name('requests.show');
    Route::post('bank-account-requests', [EbamController::class, 'createRequest'])
        ->middleware('check.permission:accounting.bank-accounts.manage')->name('requests.store');
    Route::post('bank-account-requests/{bankAccountRequest}/review', [EbamController::class, 'reviewRequest'])
        ->middleware('check.permission:accounting.bank-accounts.approve')
        ->name('ebam.bank-account-requests.review');
    Route::post('bank-account-requests/{bankAccountRequest}/execute', [EbamController::class, 'executeRequest'])
        ->middleware('check.permission:accounting.bank-accounts.manage')->name('requests.execute');
});

// XBRL Regulatory Filings — FI-GL iXBRL output (SAP FINSC_LEDGER / XBRL taxonomy)
Route::middleware(['auth:api'])->name('fi.xbrl.')->group(function () {
    // Taxonomies
    Route::get('xbrl/taxonomies', [XbrlController::class, 'taxonomiesIndex'])
        ->middleware('check.permission:accounting.xbrl.view')->name('taxonomies.index');
    Route::post('xbrl/taxonomies', [XbrlController::class, 'taxonomiesStore'])
        ->middleware('check.permission:accounting.xbrl.manage')->name('taxonomies.store');
    Route::get('xbrl/taxonomies/{xbrlTaxonomy}', [XbrlController::class, 'taxonomiesShow'])
        ->middleware('check.permission:accounting.xbrl.view')->name('taxonomies.show');
    Route::put('xbrl/taxonomies/{xbrlTaxonomy}', [XbrlController::class, 'taxonomiesUpdate'])
        ->middleware('check.permission:accounting.xbrl.manage')->name('taxonomies.update');

    // Filings
    Route::get('xbrl/filings', [XbrlController::class, 'filingsIndex'])
        ->middleware('check.permission:accounting.xbrl.view')->name('filings.index');
    Route::post('xbrl/filings', [XbrlController::class, 'filingsStore'])
        ->middleware('check.permission:accounting.xbrl.create')->name('filings.store');
    Route::get('xbrl/filings/{xbrlFiling}', [XbrlController::class, 'filingsShow'])
        ->middleware('check.permission:accounting.xbrl.view')->name('filings.show');
    Route::post('xbrl/filings/{xbrlFiling}/elements', [XbrlController::class, 'upsertElement'])
        ->middleware('check.permission:accounting.xbrl.edit')->name('filings.elements.upsert');
    Route::post('xbrl/filings/{xbrlFiling}/validate', [XbrlController::class, 'validate'])
        ->middleware('check.permission:accounting.xbrl.edit')->name('filings.validate');
    Route::post('xbrl/filings/{xbrlFiling}/generate-xml', [XbrlController::class, 'generateXml'])
        ->middleware('check.permission:accounting.xbrl.edit')->name('filings.generate-xml');
    Route::get('xbrl/filings/{xbrlFiling}/download', [XbrlController::class, 'downloadXml'])
        ->middleware('check.permission:accounting.xbrl.view')->name('filings.download');
    Route::post('xbrl/filings/{xbrlFiling}/submit', [XbrlController::class, 'submit'])
        ->middleware('check.permission:accounting.xbrl.submit')->name('filings.submit');
    Route::post('xbrl/filings/{xbrlFiling}/review', [XbrlController::class, 'review'])
        ->middleware('check.permission:accounting.xbrl.manage')
        ->name('xbrl.filings.review');
});

// IFRS 16 / ASC 842 Lease Accounting (SAP FI-LA)
Route::middleware(['auth:api'])->name('fi.leases.')->group(function () {
    Route::get('leases', [LeaseAccountingController::class, 'index'])
        ->middleware('check.permission:accounting.leases.view')->name('index');
    Route::post('leases', [LeaseAccountingController::class, 'store'])
        ->middleware('check.permission:accounting.leases.create')->name('store');
    Route::get('leases/{leaseContract}', [LeaseAccountingController::class, 'show'])
        ->middleware('check.permission:accounting.leases.view')->name('show');
    Route::get('leases/{leaseContract}/schedule', [LeaseAccountingController::class, 'schedule'])
        ->middleware('check.permission:accounting.leases.view')->name('schedule');
    Route::post('leases/{leaseContract}/post-entry', [LeaseAccountingController::class, 'postPeriodEntry'])
        ->middleware('check.permission:accounting.leases.post')->name('post-entry');
    Route::post('leases/{leaseContract}/terminate', [LeaseAccountingController::class, 'terminate'])
        ->middleware('check.permission:accounting.leases.manage')->name('terminate');
    Route::post('leases/{leaseContract}/modify', [LeaseAccountingController::class, 'modify'])
        ->middleware('check.permission:accounting.leases.manage')->name('modify');
});

// Inter-Company Asset Transfers (SAP ABUMN)
Route::middleware(['auth:api'])->name('fi.asset-transfers.')->group(function () {
    Route::get('asset-transfers', [AssetTransferController::class, 'index'])
        ->middleware('check.permission:accounting.assets.view')->name('index');
    Route::post('assets/{fixedAsset}/transfers', [AssetTransferController::class, 'store'])
        ->middleware('check.permission:accounting.assets.dispose')->name('store');
    Route::get('asset-transfers/{assetTransfer}', [AssetTransferController::class, 'show'])
        ->middleware('check.permission:accounting.assets.view')->name('show');
    Route::post('asset-transfers/{assetTransfer}/execute', [AssetTransferController::class, 'execute'])
        ->middleware('check.permission:accounting.assets.dispose')->name('execute');
    Route::post('asset-transfers/{assetTransfer}/cancel', [AssetTransferController::class, 'cancel'])
        ->middleware('check.permission:accounting.assets.dispose')->name('cancel');

    // Parallel Accounting / Multiple Ledgers — SAP FI FAGL_MIG
    Route::prefix('parallel-ledgers')->name('accounting.parallel-ledgers.')->group(function () {
        Route::get('/', [ParallelLedgerController::class, 'index'])->name('index')->middleware('check.permission:accounting.ledgers.view');
        Route::post('/', [ParallelLedgerController::class, 'store'])->name('store')->middleware('check.permission:accounting.ledgers.manage');
        Route::get('/{id}/comparison', [ParallelLedgerController::class, 'comparison'])->name('comparison')->middleware('check.permission:accounting.ledgers.view');
        Route::post('/{id}/post/{journalEntryId}', [ParallelLedgerController::class, 'postEntry'])->name('post-entry')->middleware('check.permission:accounting.ledgers.manage');
    });

    // CO Reconciliation Ledger — SAP KALC
    Route::prefix('co-reconciliation')->name('accounting.co-reconciliation.')->group(function () {
        Route::get('/', [CoReconciliationController::class, 'index'])->name('index')->middleware('check.permission:accounting.co.view');
        Route::get('/{id}', [CoReconciliationController::class, 'show'])->name('show')->middleware('check.permission:accounting.co.view');
        Route::post('/reconcile-assessment', [CoReconciliationController::class, 'reconcileAssessment'])->name('reconcile-assessment')->middleware('check.permission:accounting.co.post');
        Route::post('/reconcile-distribution', [CoReconciliationController::class, 'reconcileDistribution'])->name('reconcile-distribution')->middleware('check.permission:accounting.co.post');
    });
});

// Withholding Tax (SAP F.67/F.68)
Route::middleware(['auth:api'])->name('fi.wht.')->group(function () {
    // WHT code master
    Route::get('withholding-tax/codes', [WithholdingTaxController::class, 'indexCodes'])
        ->middleware('check.permission:accounting.wht.view')->name('codes.index');
    Route::post('withholding-tax/codes', [WithholdingTaxController::class, 'storeCode'])
        ->middleware('check.permission:accounting.wht.manage')->name('codes.store');
    Route::get('withholding-tax/codes/{withholdingTaxCode}', [WithholdingTaxController::class, 'showCode'])
        ->middleware('check.permission:accounting.wht.view')->name('codes.show');
    Route::put('withholding-tax/codes/{withholdingTaxCode}', [WithholdingTaxController::class, 'updateCode'])
        ->middleware('check.permission:accounting.wht.manage')->name('codes.update');
    Route::delete('withholding-tax/codes/{withholdingTaxCode}', [WithholdingTaxController::class, 'destroyCode'])
        ->middleware('check.permission:accounting.wht.manage')->name('codes.destroy');

    // Apply / calculate
    Route::post('withholding-tax/codes/{withholdingTaxCode}/calculate', [WithholdingTaxController::class, 'calculate'])
        ->middleware('check.permission:accounting.wht.view')->name('calculate');
    Route::post('withholding-tax/codes/{withholdingTaxCode}/apply', [WithholdingTaxController::class, 'apply'])
        ->middleware('check.permission:accounting.wht.post')->name('apply');

    // Certificate issuance
    Route::post('withholding-tax/lines/{withholdingTaxLine}/certificate', [WithholdingTaxController::class, 'issueCertificate'])
        ->middleware('check.permission:accounting.wht.manage')->name('lines.certificate');

    // Reporting
    Route::get('withholding-tax/summary', [WithholdingTaxController::class, 'summary'])
        ->middleware('check.permission:accounting.wht.view')->name('summary');
});

// Payment Tolerance & Clearing Variance (SAP FI OBA3/OBB8)
Route::middleware(['auth:api'])->name('fi.tolerance.')->group(function () {
    // Tolerance groups
    Route::get('payment-tolerance/groups', [PaymentToleranceController::class, 'indexGroups'])
        ->middleware('check.permission:accounting.tolerance.view')->name('groups.index');
    Route::post('payment-tolerance/groups', [PaymentToleranceController::class, 'storeGroup'])
        ->middleware('check.permission:accounting.tolerance.manage')->name('groups.store');
    Route::get('payment-tolerance/groups/{paymentToleranceGroup}', [PaymentToleranceController::class, 'showGroup'])
        ->middleware('check.permission:accounting.tolerance.view')->name('groups.show');
    Route::put('payment-tolerance/groups/{paymentToleranceGroup}', [PaymentToleranceController::class, 'updateGroup'])
        ->middleware('check.permission:accounting.tolerance.manage')->name('groups.update');
    Route::delete('payment-tolerance/groups/{paymentToleranceGroup}', [PaymentToleranceController::class, 'destroyGroup'])
        ->middleware('check.permission:accounting.tolerance.manage')->name('groups.destroy');

    // Per-currency threshold items
    Route::post('payment-tolerance/groups/{paymentToleranceGroup}/items', [PaymentToleranceController::class, 'upsertItem'])
        ->middleware('check.permission:accounting.tolerance.manage')->name('items.upsert');
    Route::delete('payment-tolerance/groups/{paymentToleranceGroup}/items/{paymentToleranceItem}', [PaymentToleranceController::class, 'removeItem'])
        ->middleware('check.permission:accounting.tolerance.manage')->name('items.destroy');

    // Evaluate (preview — no side effects)
    Route::post('payment-tolerance/groups/{paymentToleranceGroup}/evaluate', [PaymentToleranceController::class, 'evaluate'])
        ->middleware('check.permission:accounting.tolerance.view')->name('evaluate');

    // Clear (write-off tolerance difference to GL)
    Route::post('payment-tolerance/groups/{paymentToleranceGroup}/clear', [PaymentToleranceController::class, 'clearDifference'])
        ->middleware('check.permission:accounting.tolerance.post')->name('clear');

    // Difference posts list + variance report
    Route::get('payment-tolerance/difference-posts', [PaymentToleranceController::class, 'indexDifferencePosts'])
        ->middleware('check.permission:accounting.tolerance.view')->name('posts.index');
    Route::get('payment-tolerance/variance-summary', [PaymentToleranceController::class, 'varianceSummary'])
        ->middleware('check.permission:accounting.tolerance.view')->name('variance-summary');
});

// Installment Payment Plans (SAP FI-AR F-36 / FI-AP F-59)
Route::middleware(['auth:api'])->name('fi.installments.')->group(function () {
    Route::get('installment-plans', [InstallmentPlanController::class, 'index'])
        ->middleware('check.permission:accounting.installments.view')->name('index');
    Route::post('installment-plans', [InstallmentPlanController::class, 'store'])
        ->middleware('check.permission:accounting.installments.create')->name('store');
    // Static action routes BEFORE the {installmentPlan} wildcard to avoid misrouting
    Route::post('installment-plans/mark-overdue', [InstallmentPlanController::class, 'markOverdue'])
        ->middleware('check.permission:accounting.installments.manage')->name('mark-overdue');
    Route::get('installment-plans/upcoming', [InstallmentPlanController::class, 'upcoming'])
        ->middleware('check.permission:accounting.installments.view')->name('upcoming');
    Route::get('installment-plans/overdue-summary', [InstallmentPlanController::class, 'overdueSummary'])
        ->middleware('check.permission:accounting.installments.view')->name('overdue-summary');
    Route::get('installment-plans/{installmentPlan}', [InstallmentPlanController::class, 'show'])
        ->middleware('check.permission:accounting.installments.view')->name('show');
    Route::post('installment-plans/{installmentPlan}/activate', [InstallmentPlanController::class, 'activate'])
        ->middleware('check.permission:accounting.installments.manage')->name('activate');
    Route::post('installment-plans/{installmentPlan}/cancel', [InstallmentPlanController::class, 'cancel'])
        ->middleware('check.permission:accounting.installments.manage')->name('cancel');
    Route::post('installment-plans/{installmentPlan}/schedules/{installmentSchedule}/pay', [InstallmentPlanController::class, 'recordPayment'])
        ->middleware('check.permission:accounting.installments.post')->name('schedules.pay');
});

// House Banks & Payment Advices (SAP FI-BL FI12 / FBZP)
Route::middleware(['auth:api'])->name('fi.housebank.')->group(function () {
    // House Bank master data (FI12)
    Route::get('house-banks', [HouseBankController::class, 'index'])
        ->middleware('check.permission:accounting.housebank.view')->name('banks.index');
    Route::post('house-banks', [HouseBankController::class, 'store'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.store');
    Route::get('house-banks/{houseBank}', [HouseBankController::class, 'show'])
        ->middleware('check.permission:accounting.housebank.view')->name('banks.show');
    Route::put('house-banks/{houseBank}', [HouseBankController::class, 'update'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.update');
    Route::delete('house-banks/{houseBank}', [HouseBankController::class, 'destroy'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.destroy');

    // Bank accounts within a house bank
    Route::post('house-banks/{houseBank}/accounts', [HouseBankController::class, 'addAccount'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.accounts.store');
    Route::put('house-banks/{houseBank}/accounts/{houseBankAccount}', [HouseBankController::class, 'updateAccount'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.accounts.update');
    Route::delete('house-banks/{houseBank}/accounts/{houseBankAccount}', [HouseBankController::class, 'removeAccount'])
        ->middleware('check.permission:accounting.housebank.manage')->name('banks.accounts.destroy');

    // Payment Advices (FBZP) — static routes before parameterized ones
    Route::get('payment-advices', [HouseBankController::class, 'indexAdvices'])
        ->middleware('check.permission:accounting.housebank.view')->name('advices.index');
    Route::post('payment-advices', [HouseBankController::class, 'storeAdvice'])
        ->middleware('check.permission:accounting.housebank.create')->name('advices.store');
    Route::get('payment-advices/outstanding-summary', [HouseBankController::class, 'outstandingSummary'])
        ->middleware('check.permission:accounting.housebank.view')->name('advices.outstanding-summary');
    Route::get('payment-advices/{paymentAdvice}', [HouseBankController::class, 'showAdvice'])
        ->middleware('check.permission:accounting.housebank.view')->name('advices.show');
    Route::post('payment-advices/{paymentAdvice}/send', [HouseBankController::class, 'sendAdvice'])
        ->middleware('check.permission:accounting.housebank.manage')->name('advices.send');
    Route::post('payment-advices/{paymentAdvice}/acknowledge', [HouseBankController::class, 'acknowledgeAdvice'])
        ->middleware('check.permission:accounting.housebank.manage')->name('advices.acknowledge');
    Route::post('payment-advices/{paymentAdvice}/cancel', [HouseBankController::class, 'cancelAdvice'])
        ->middleware('check.permission:accounting.housebank.manage')->name('advices.cancel');
});

/*
|--------------------------------------------------------------------------
| Intercompany Reconciliation — SAP FI F.13 / FBICN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('ic-reconciliation')->name('accounting.ic-rec.')->group(function (): void {
    Route::get('/sessions', [IntercompanyReconciliationController::class, 'index'])->name('index');
    Route::post('/sessions', [IntercompanyReconciliationController::class, 'createSession'])->name('sessions.store');
    Route::get('/sessions/{icReconciliationSession}', [IntercompanyReconciliationController::class, 'show'])->name('sessions.show');
    Route::post('/sessions/{icReconciliationSession}/load-items', [IntercompanyReconciliationController::class, 'loadItems'])->name('sessions.load-items');
    Route::post('/sessions/{icReconciliationSession}/auto-match', [IntercompanyReconciliationController::class, 'autoMatch'])->name('sessions.auto-match');
    Route::post('/sessions/{icReconciliationSession}/manual-match', [IntercompanyReconciliationController::class, 'manualMatch'])->name('sessions.manual-match');
    Route::post('/sessions/{icReconciliationSession}/close', [IntercompanyReconciliationController::class, 'close'])->name('sessions.close');
});

/*
|--------------------------------------------------------------------------
| FX Derivatives & Hedge Accounting — SAP TRM / IFRS 9
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('fx-forwards')->name('accounting.fx.')->group(function (): void {
    Route::get('/', [FxDerivativeController::class, 'index'])->name('index');
    Route::post('/', [FxDerivativeController::class, 'store'])->name('store');
    Route::get('/{fxForward}', [FxDerivativeController::class, 'show'])->name('show');
    Route::post('/{fxForward}/designate-hedge', [FxDerivativeController::class, 'designateHedge'])->name('designate-hedge');
    Route::post('/{fxForward}/dedesignate-hedge', [FxDerivativeController::class, 'dedesignateHedge'])->name('dedesignate-hedge');
    Route::post('/{fxForward}/valuate', [FxDerivativeController::class, 'valuate'])->name('valuate');
    Route::post('/{fxForward}/settle', [FxDerivativeController::class, 'settle'])->name('settle');
});
