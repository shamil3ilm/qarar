<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tax\EwayBillController;
use App\Http\Controllers\Api\V1\Tax\GstController;
use App\Http\Controllers\Api\V1\Tax\GstReturnController;
use App\Http\Controllers\Api\V1\Tax\TdsComplianceController;
use App\Http\Controllers\Api\V1\Tax\TdsController;
use App\Http\Controllers\Api\V1\Tax\VatComplianceController;
use App\Http\Controllers\Api\V1\Tax\VatReturnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax Compliance Routes — VAT (GCC), GST (India), TDS/TCS (India)
|--------------------------------------------------------------------------
|
| All routes require JWT authentication and a verified organization context.
| Additional permission checks are applied per route group.
|
*/

// -------------------------------------------------------------------------
// GCC VAT Returns
// -------------------------------------------------------------------------
Route::prefix('vat-returns')->name('tax.vat.')->group(function () {

    Route::middleware('check.permission:tax.vat.view')->group(function () {
        Route::get('/', [VatReturnController::class, 'index'])->name('index');
        Route::get('/transactions', [VatReturnController::class, 'indexTransactions'])->name('transactions.index');
        Route::get('/{vatReturnPeriod}', [VatReturnController::class, 'show'])->name('show');
        Route::get('/{vatReturnPeriod}/export', [VatReturnController::class, 'exportReturn'])->name('export');
    });

    Route::middleware('check.permission:tax.vat.manage')->group(function () {
        Route::post('/', [VatReturnController::class, 'store'])->name('store');
        Route::post('/{vatReturnPeriod}/build-boxes', [VatReturnController::class, 'buildBoxes'])->name('build-boxes');
        Route::post('/{vatReturnPeriod}/submit', [VatReturnController::class, 'submit'])->name('submit');
        Route::post('/transactions', [VatReturnController::class, 'storeTransaction'])->name('transactions.store');
    });
});

// -------------------------------------------------------------------------
// India GST Compliance
// -------------------------------------------------------------------------
Route::prefix('gst')->name('tax.gst.')->group(function () {

    Route::middleware('check.permission:tax.gst.view')->group(function () {
        Route::get('/registrations', [GstController::class, 'indexRegistrations'])->name('registrations.index');
        Route::get('/ewaybills', [GstController::class, 'indexEwayBills'])->name('ewaybills.index');
        Route::get('/ewaybills/{ewaybill}', [GstController::class, 'showEwayBill'])->name('ewaybills.show');
        Route::get('/itc-ledger', [GstController::class, 'itcLedger'])->name('itc-ledger');
    });

    Route::middleware('check.permission:tax.gst.manage')->group(function () {
        Route::post('/registrations', [GstController::class, 'storeRegistration'])->name('registrations.store');

        // GSTR-1
        Route::post('/gstr1/prepare', [GstController::class, 'prepareGstr1'])->name('gstr1.prepare');
        Route::post('/gstr1/{gstr1Return}/file', [GstController::class, 'fileGstr1'])->name('gstr1.file');

        // GSTR-3B
        Route::post('/gstr3b/prepare', [GstController::class, 'prepareGstr3b'])->name('gstr3b.prepare');
        Route::post('/gstr3b/{gstr3bReturn}/file', [GstController::class, 'fileGstr3b'])->name('gstr3b.file');

        // E-way bills
        Route::post('/ewaybills', [GstController::class, 'generateEwayBill'])->name('ewaybills.store');
        Route::post('/ewaybills/{ewaybill}/cancel', [GstController::class, 'cancelEwayBill'])->name('ewaybills.cancel');

        // ITC ledger
        Route::post('/itc-ledger', [GstController::class, 'updateItcLedger'])->name('itc-ledger.update');
    });
});

// -------------------------------------------------------------------------
// India TDS / TCS
// -------------------------------------------------------------------------
Route::prefix('tds')->name('tax.tds.')->group(function () {

    Route::middleware('check.permission:tax.tds.view')->group(function () {
        Route::get('/sections', [TdsController::class, 'indexSections'])->name('sections.index');
        Route::get('/configuration', [TdsController::class, 'getConfiguration'])->name('configuration.show');
        Route::get('/deductions', [TdsController::class, 'indexDeductions'])->name('deductions.index');
        Route::get('/certificates', [TdsController::class, 'indexCertificates'])->name('certificates.index');
        Route::get('/returns', [TdsController::class, 'indexReturns'])->name('returns.index');
        Route::get('/tcs/configurations', [TdsController::class, 'indexTcsConfigurations'])->name('tcs.configurations.index');
        Route::get('/tcs/collections', [TdsController::class, 'indexTcsCollections'])->name('tcs.collections.index');
    });

    Route::middleware('check.permission:tax.tds.manage')->group(function () {
        Route::post('/calculate', [TdsController::class, 'calculateTds'])->name('calculate');
        Route::post('/configuration', [TdsController::class, 'saveConfiguration'])->name('configuration.save');
        Route::post('/deductions', [TdsController::class, 'storeDeduction'])->name('deductions.store');
        Route::post('/certificates/generate', [TdsController::class, 'generateCertificate'])->name('certificates.generate');
        Route::post('/returns/prepare', [TdsController::class, 'prepareReturn'])->name('returns.prepare');
        Route::post('/returns/{tdsReturn}/file', [TdsController::class, 'fileReturn'])->name('returns.file');
        Route::post('/tcs/configurations', [TdsController::class, 'saveTcsConfiguration'])->name('tcs.configurations.save');
        Route::post('/tcs/collections', [TdsController::class, 'storeTcsCollection'])->name('tcs.collections.store');
    });
});

// -------------------------------------------------------------------------
// E-Way Bills (India GST)
// -------------------------------------------------------------------------
Route::prefix('ewaybills')->name('tax.ewaybills.')->group(function () {
    Route::middleware('check.permission:tax.gst.view')->group(function () {
        Route::get('/', [EwayBillController::class, 'index'])->name('index');
        Route::get('/{id}', [EwayBillController::class, 'show'])->name('show');
    });
    Route::middleware('check.permission:tax.gst.manage')->group(function () {
        Route::post('/', [EwayBillController::class, 'store'])->name('store');
        Route::post('/{id}/cancel', [EwayBillController::class, 'cancel'])->name('cancel');
    });
});

// -------------------------------------------------------------------------
// GST Returns (India)
// -------------------------------------------------------------------------
Route::prefix('gst-returns')->name('tax.gst-returns.')->group(function () {
    Route::middleware('check.permission:tax.gst.view')->group(function () {
        Route::get('/', [GstReturnController::class, 'index'])->name('index');
        Route::get('/{id}', [GstReturnController::class, 'show'])->name('show');
    });
    Route::middleware('check.permission:tax.gst.manage')->group(function () {
        Route::post('/gstr1/generate', [GstReturnController::class, 'generateGstr1'])->name('gstr1.generate');
        Route::post('/gstr3b/generate', [GstReturnController::class, 'generateGstr3b'])->name('gstr3b.generate');
        Route::post('/{id}/file', [GstReturnController::class, 'file'])->name('file');
    });
});

// -------------------------------------------------------------------------
// TDS Compliance (India)
// -------------------------------------------------------------------------
Route::prefix('tds-compliance')->name('tax.tds-compliance.')->group(function () {
    Route::middleware('check.permission:tax.tds.view')->group(function () {
        Route::get('/', [TdsComplianceController::class, 'index'])->name('index');
        Route::get('/deductions', [TdsComplianceController::class, 'listDeductions'])->name('deductions');
        Route::get('/pending-report', [TdsComplianceController::class, 'pendingReport'])->name('pending-report');
    });
    Route::middleware('check.permission:tax.tds.manage')->group(function () {
        Route::post('/', [TdsComplianceController::class, 'store'])->name('store');
        Route::post('/{id}/deposit', [TdsComplianceController::class, 'deposit'])->name('deposit');
    });
});

// -------------------------------------------------------------------------
// VAT Compliance (GCC)
// -------------------------------------------------------------------------
Route::prefix('vat-compliance')->name('tax.vat-compliance.')->group(function () {
    Route::middleware('check.permission:tax.vat.view')->group(function () {
        Route::get('/', [VatComplianceController::class, 'index'])->name('index');
        Route::get('/{id}', [VatComplianceController::class, 'show'])->name('show');
    });
    Route::middleware('check.permission:tax.vat.manage')->group(function () {
        Route::post('/generate', [VatComplianceController::class, 'generate'])->name('generate');
        Route::post('/{id}/file', [VatComplianceController::class, 'file'])->name('file');
    });
});
