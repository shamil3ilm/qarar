<?php

use App\Http\Controllers\Api\V1\Billing\BillingInvoiceController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Billing\UsageController;
use Illuminate\Support\Facades\Route;

// Subscription Plans
Route::prefix('plans')->group(function () {
    Route::get('/', [SubscriptionPlanController::class, 'index'])
        ->middleware('check.permission:billing.plans.view');
    Route::post('/', [SubscriptionPlanController::class, 'store'])
        ->middleware('check.permission:billing.plans.create');
    Route::get('/{plan}', [SubscriptionPlanController::class, 'show'])
        ->middleware('check.permission:billing.plans.view');
    Route::put('/{plan}', [SubscriptionPlanController::class, 'update'])
        ->middleware('check.permission:billing.plans.update');
    Route::delete('/{plan}', [SubscriptionPlanController::class, 'destroy'])
        ->middleware('check.permission:billing.plans.delete');
});

// Subscriptions
Route::prefix('subscriptions')->group(function () {
    Route::get('/current', [SubscriptionController::class, 'current'])
        ->middleware('check.permission:billing.subscriptions.view');
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])
        ->middleware('check.permission:billing.subscriptions.create');
    Route::post('/change-plan', [SubscriptionController::class, 'changePlan'])
        ->middleware('check.permission:billing.subscriptions.update');
    Route::post('/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('check.permission:billing.subscriptions.update');
    Route::get('/addons', [SubscriptionController::class, 'availableAddons'])
        ->middleware('check.permission:billing.subscriptions.view');
    Route::post('/addons', [SubscriptionController::class, 'purchaseAddon'])
        ->middleware('check.permission:billing.subscriptions.update');
});

// Billing Invoices
Route::prefix('invoices')->group(function () {
    Route::get('/', [BillingInvoiceController::class, 'index'])
        ->middleware('check.permission:billing.invoices.view');
    Route::get('/{invoice}', [BillingInvoiceController::class, 'show'])
        ->middleware('check.permission:billing.invoices.view');
    Route::post('/{invoice}/pay', [BillingInvoiceController::class, 'pay'])
        ->middleware('check.permission:billing.invoices.pay');
});

// Usage
Route::prefix('usage')->group(function () {
    Route::get('/', [UsageController::class, 'index'])
        ->middleware('check.permission:billing.usage.view');
    Route::get('/summary', [UsageController::class, 'summary']);
    Route::get('/history', [UsageController::class, 'history']);
    Route::get('/alerts', [UsageController::class, 'alerts'])
        ->middleware('check.permission:billing.usage.view');
});
