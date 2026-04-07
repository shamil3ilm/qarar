<?php

use App\Http\Controllers\Api\V1\Sales\BackorderController;
use App\Http\Controllers\Api\V1\Sales\BillingDueListController;
use App\Http\Controllers\Api\V1\Sales\BillingPlanController;
use App\Http\Controllers\Api\V1\Sales\RebateController;
use App\Http\Controllers\Api\V1\Sales\ContactController;
use App\Http\Controllers\Api\V1\Sales\CpqController;
use App\Http\Controllers\Api\V1\Sales\CustomerAdvanceController;
use App\Http\Controllers\Api\V1\Sales\HandlingUnitController;
use App\Http\Controllers\Api\V1\Sales\InvoiceController;
use App\Http\Controllers\Api\V1\Sales\PaymentReceivedController;
use App\Http\Controllers\Api\V1\Sales\QuotationController;
use App\Http\Controllers\Api\V1\Sales\RevenueRecognitionController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderCostingController;
use App\Http\Controllers\Api\V1\Sales\ShippingRouteController;
use App\Http\Controllers\Api\V1\Sales\ThirdPartyOrderController;
use App\Http\Controllers\Api\V1\Sales\WalletController;
use App\Http\Controllers\Api\V1\Sales\CreditNoteController;
use App\Http\Controllers\Api\V1\Sales\RefundController;
use App\Http\Controllers\Api\V1\Sales\SalesReturnController;
use App\Http\Controllers\Api\V1\Sales\PromotionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sales API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/sales
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Contacts (Customers/Suppliers)
    |--------------------------------------------------------------------------
    */
    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->middleware('check.permission:sales.contacts.view')->name('sales.contacts.index');
        Route::post('/', [ContactController::class, 'store'])->middleware('check.permission:sales.contacts.create')->name('sales.contacts.store');
        Route::get('/{contact}', [ContactController::class, 'show'])->middleware('check.permission:sales.contacts.view')->name('sales.contacts.show');
        Route::put('/{contact}', [ContactController::class, 'update'])->middleware('check.permission:sales.contacts.edit')->name('sales.contacts.update');
        Route::delete('/{contact}', [ContactController::class, 'destroy'])->middleware('check.permission:sales.contacts.delete')->name('sales.contacts.destroy');
        Route::get('/{contact}/statement', [ContactController::class, 'statement'])->middleware('check.permission:sales.contacts.view')->name('sales.contacts.statement');
        Route::get('/{contact}/balance', [ContactController::class, 'balance'])->middleware('check.permission:sales.contacts.view')->name('sales.contacts.balance');
        Route::patch('/{contact}/payment-block', [ContactController::class, 'setPaymentBlock'])->middleware('check.permission:sales.contacts.edit')->name('sales.contacts.payment-block');
    });

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('check.permission:sales.invoices.view')->name('sales.invoices.index');
        Route::post('/', [InvoiceController::class, 'store'])->middleware('check.permission:sales.invoices.create')->name('sales.invoices.store');
        Route::get('/summary', [InvoiceController::class, 'summary'])->middleware('check.permission:sales.invoices.view')->name('sales.invoices.summary');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('check.permission:sales.invoices.view')->name('sales.invoices.show');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])->middleware('check.permission:sales.invoices.edit')->name('sales.invoices.update');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->middleware('check.permission:sales.invoices.delete')->name('sales.invoices.destroy');
        Route::post('/{invoice}/send', [InvoiceController::class, 'send'])->middleware(['check.permission:sales.invoices.send', 'throttle:api-financial'])->name('sales.invoices.send');
        Route::post('/{invoice}/void', [InvoiceController::class, 'void'])->middleware(['check.permission:sales.invoices.void', 'throttle:api-financial'])->name('sales.invoices.void');
        Route::post('/{invoice}/credit-note', [InvoiceController::class, 'createCreditNote'])->middleware('check.permission:sales.invoices.credit-note')->name('sales.invoices.credit-note');
        Route::get('/{invoice}/compliance-status', [InvoiceController::class, 'complianceStatus'])->middleware('check.permission:sales.invoices.view')->name('sales.invoices.compliance-status');
    });

    /*
    |--------------------------------------------------------------------------
    | Quotations
    |--------------------------------------------------------------------------
    */
    Route::prefix('quotations')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->middleware('check.permission:sales.quotations.view')->name('sales.quotations.index');
        Route::post('/', [QuotationController::class, 'store'])->middleware('check.permission:sales.quotations.create')->name('sales.quotations.store');
        Route::get('/{quotation}', [QuotationController::class, 'show'])->middleware('check.permission:sales.quotations.view')->name('sales.quotations.show');
        Route::put('/{quotation}', [QuotationController::class, 'update'])->middleware('check.permission:sales.quotations.edit')->name('sales.quotations.update');
        Route::delete('/{quotation}', [QuotationController::class, 'destroy'])->middleware('check.permission:sales.quotations.delete')->name('sales.quotations.destroy');
        Route::post('/{quotation}/send', [QuotationController::class, 'send'])->middleware('check.permission:sales.quotations.send')->name('sales.quotations.send');
        Route::post('/{quotation}/review', [QuotationController::class, 'review'])->middleware('check.permission:sales.quotations.edit')->name('sales.quotations.review');
        Route::post('/{quotation}/convert', [QuotationController::class, 'convert'])->middleware('check.permission:sales.quotations.convert')->name('sales.quotations.convert');
    });

    /*
    |--------------------------------------------------------------------------
    | Sales Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('sales-orders')->group(function () {
        Route::get('/', [SalesOrderController::class, 'index'])->middleware('check.permission:sales.orders.view')->name('sales.orders.index');
        Route::post('/', [SalesOrderController::class, 'store'])->middleware('check.permission:sales.orders.create')->name('sales.orders.store');
        Route::get('/{salesOrder}', [SalesOrderController::class, 'show'])->middleware('check.permission:sales.orders.view')->name('sales.orders.show');
        Route::put('/{salesOrder}', [SalesOrderController::class, 'update'])->middleware('check.permission:sales.orders.edit')->name('sales.orders.update');
        Route::delete('/{salesOrder}', [SalesOrderController::class, 'destroy'])->middleware('check.permission:sales.orders.delete')->name('sales.orders.destroy');
        Route::post('/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->middleware('check.permission:sales.orders.confirm')->name('sales.orders.confirm');
        Route::post('/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('check.permission:sales.orders.cancel')->name('sales.orders.cancel');
        Route::post('/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice'])->middleware('check.permission:sales.orders.convert')->name('sales.orders.convert-to-invoice');
        Route::post('/{salesOrder}/create-delivery', [SalesOrderController::class, 'createDelivery'])->middleware('check.permission:sales.orders.deliver')->name('sales.orders.create-delivery');
        Route::get('/{salesOrder}/credit-check', [SalesOrderController::class, 'creditCheck'])->middleware('check.permission:sales.orders.view')->name('sales.orders.credit-check');
    });

    /*
    |--------------------------------------------------------------------------
    | Payments Received
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments-received')->group(function () {
        Route::get('/', [PaymentReceivedController::class, 'index'])->middleware('check.permission:sales.payments.view')->name('sales.payments.index');
        Route::post('/', [PaymentReceivedController::class, 'store'])->middleware('check.permission:sales.payments.create')->name('sales.payments.store');
        Route::get('/summary', [PaymentReceivedController::class, 'summary'])->middleware('check.permission:sales.payments.view')->name('sales.payments.summary');
        Route::get('/open-items', [PaymentReceivedController::class, 'openItems'])->middleware('check.permission:sales.payments.view')->name('sales.payments.open-items');
        Route::post('/clear-open-items', [PaymentReceivedController::class, 'clearOpenItems'])->middleware('check.permission:sales.payments.allocate')->name('sales.payments.clear-open-items');
        Route::get('/{paymentReceived}', [PaymentReceivedController::class, 'show'])->middleware('check.permission:sales.payments.view')->name('sales.payments.show');
        Route::delete('/{paymentReceived}', [PaymentReceivedController::class, 'destroy'])->middleware('check.permission:sales.payments.delete')->name('sales.payments.destroy');
        Route::post('/{paymentReceived}/complete', [PaymentReceivedController::class, 'complete'])->middleware(['check.permission:sales.payments.complete', 'throttle:api-financial'])->name('sales.payments.complete');
        Route::post('/{paymentReceived}/void', [PaymentReceivedController::class, 'void'])->middleware(['check.permission:sales.payments.void', 'throttle:api-financial'])->name('sales.payments.void');
        Route::post('/{paymentReceived}/bounce', [PaymentReceivedController::class, 'bounce'])->middleware('check.permission:sales.payments.void')->name('sales.payments.bounce');
        Route::post('/{paymentReceived}/allocate', [PaymentReceivedController::class, 'allocate'])->middleware('check.permission:sales.payments.allocate')->name('sales.payments.allocate');
    });

    /*
    |--------------------------------------------------------------------------
    | Wallets
    |--------------------------------------------------------------------------
    */
    Route::prefix('wallets')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->middleware('check.permission:sales.wallets.view')->name('sales.wallets.index');
        Route::get('/contact/{contactId}/balance', [WalletController::class, 'balance'])->middleware('check.permission:sales.wallets.view')->name('sales.wallets.balance');
        Route::get('/{wallet}', [WalletController::class, 'show'])->middleware('check.permission:sales.wallets.view')->name('sales.wallets.show');
        Route::post('/{wallet}/credit', [WalletController::class, 'credit'])->middleware('check.permission:sales.wallets.credit')->name('sales.wallets.credit');
        Route::post('/{wallet}/debit', [WalletController::class, 'debit'])->middleware('check.permission:sales.wallets.debit')->name('sales.wallets.debit');
        Route::post('/{wallet}/adjust', [WalletController::class, 'adjust'])->middleware('check.permission:sales.wallets.adjust')->name('sales.wallets.adjust');
        Route::get('/{wallet}/statement', [WalletController::class, 'statement'])->middleware('check.permission:sales.wallets.view')->name('sales.wallets.statement');
    });

    /*
    |--------------------------------------------------------------------------
    | Credit Notes
    |--------------------------------------------------------------------------
    */
    Route::prefix('credit-notes')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index'])->middleware('check.permission:sales.credit-notes.view')->name('sales.credit-notes.index');
        Route::post('/', [CreditNoteController::class, 'store'])->middleware('check.permission:sales.credit-notes.create')->name('sales.credit-notes.store');
        Route::get('/{creditNote}', [CreditNoteController::class, 'show'])->middleware('check.permission:sales.credit-notes.view')->name('sales.credit-notes.show');
        Route::post('/{creditNote}/approve', [CreditNoteController::class, 'approve'])->middleware('check.permission:sales.credit-notes.approve')->name('sales.credit-notes.approve');
        Route::post('/{creditNote}/apply', [CreditNoteController::class, 'apply'])->middleware('check.permission:sales.credit-notes.apply')->name('sales.credit-notes.apply');
        Route::post('/{creditNote}/void', [CreditNoteController::class, 'void'])->middleware('check.permission:sales.credit-notes.void')->name('sales.credit-notes.void');
    });

    /*
    |--------------------------------------------------------------------------
    | Refunds
    |--------------------------------------------------------------------------
    */
    Route::prefix('refunds')->group(function () {
        Route::get('/', [RefundController::class, 'index'])->middleware('check.permission:sales.refunds.view')->name('sales.refunds.index');
        Route::post('/', [RefundController::class, 'store'])->middleware('check.permission:sales.refunds.create')->name('sales.refunds.store');
        Route::get('/{refund}', [RefundController::class, 'show'])->middleware('check.permission:sales.refunds.view')->name('sales.refunds.show');
        Route::post('/{refund}/approve', [RefundController::class, 'approve'])->middleware('check.permission:sales.refunds.approve')->name('sales.refunds.approve');
        Route::post('/{refund}/process', [RefundController::class, 'process'])->middleware('check.permission:sales.refunds.process')->name('sales.refunds.process');
        Route::post('/{refund}/cancel', [RefundController::class, 'cancel'])->middleware('check.permission:sales.refunds.cancel')->name('sales.refunds.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Sales Returns & Exchanges
    |--------------------------------------------------------------------------
    */
    Route::prefix('returns')->group(function () {
        Route::get('/', [SalesReturnController::class, 'index'])->middleware('check.permission:sales.returns.view')->name('sales.returns.index');
        Route::post('/', [SalesReturnController::class, 'store'])->middleware('check.permission:sales.returns.create')->name('sales.returns.store');
        Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('check.permission:sales.returns.view')->name('sales.returns.show');
        Route::post('/{salesReturn}/review', [SalesReturnController::class, 'review'])->middleware('check.permission:sales.returns.approve')->name('sales.returns.review');
        Route::post('/{salesReturn}/receive', [SalesReturnController::class, 'receiveItems'])->middleware('check.permission:sales.returns.receive')->name('sales.returns.receive');
        Route::post('/{salesReturn}/inspect', [SalesReturnController::class, 'inspect'])->middleware('check.permission:sales.returns.inspect')->name('sales.returns.inspect');
        Route::post('/{salesReturn}/resolve', [SalesReturnController::class, 'resolve'])->middleware('check.permission:sales.returns.resolve')->name('sales.returns.resolve');
        Route::post('/{salesReturn}/exchange', [SalesReturnController::class, 'exchange'])->middleware('check.permission:sales.returns.exchange')->name('sales.returns.exchange');
    });

    /*
    |--------------------------------------------------------------------------
    | Customer Advance Payments
    |--------------------------------------------------------------------------
    */
    Route::prefix('customer-advances')->group(function () {
        Route::get('/', [CustomerAdvanceController::class, 'index'])->middleware('check.permission:sales.customer-advances.view')->name('sales.customer-advances.index');
        Route::post('/', [CustomerAdvanceController::class, 'store'])->middleware('check.permission:sales.customer-advances.create')->name('sales.customer-advances.store');
        Route::get('/contact/{contactId}/open', [CustomerAdvanceController::class, 'openAdvances'])->middleware('check.permission:sales.customer-advances.view')->name('sales.customer-advances.open');
        Route::get('/{advancePayment}', [CustomerAdvanceController::class, 'show'])->middleware('check.permission:sales.customer-advances.view')->name('sales.customer-advances.show');
        Route::delete('/{advancePayment}', [CustomerAdvanceController::class, 'destroy'])->middleware('check.permission:sales.customer-advances.delete')->name('sales.customer-advances.destroy');
        Route::post('/{advancePayment}/apply', [CustomerAdvanceController::class, 'applyToInvoice'])->middleware('check.permission:sales.customer-advances.apply')->name('sales.customer-advances.apply');
        Route::post('/{advancePayment}/refund', [CustomerAdvanceController::class, 'refund'])->middleware('check.permission:sales.customer-advances.refund')->name('sales.customer-advances.refund');
    });

    /*
    |--------------------------------------------------------------------------
    | Promotions & Coupons
    |--------------------------------------------------------------------------
    */
    Route::prefix('promotions')->group(function () {
        Route::get('/', [PromotionController::class, 'index'])->middleware('check.permission:sales.promotions.view')->name('sales.promotions.index');
        Route::post('/', [PromotionController::class, 'store'])->middleware('check.permission:sales.promotions.create')->name('sales.promotions.store');
        Route::post('/validate-code', [PromotionController::class, 'validateCode'])->middleware('check.permission:sales.promotions.view')->name('sales.promotions.validate-code');
        Route::get('/{promotion}', [PromotionController::class, 'show'])->middleware('check.permission:sales.promotions.view')->name('sales.promotions.show');
        Route::put('/{promotion}', [PromotionController::class, 'update'])->middleware('check.permission:sales.promotions.edit')->name('sales.promotions.update');
        Route::delete('/{promotion}', [PromotionController::class, 'destroy'])->middleware('check.permission:sales.promotions.delete')->name('sales.promotions.destroy');
        Route::post('/{promotion}/generate-coupons', [PromotionController::class, 'generateCoupons'])->middleware('check.permission:sales.promotions.edit')->name('sales.promotions.generate-coupons');
        Route::get('/{promotion}/coupons', [PromotionController::class, 'coupons'])->middleware('check.permission:sales.promotions.view')->name('sales.promotions.coupons');
        Route::get('/{promotion}/analytics', [PromotionController::class, 'analytics'])->middleware('check.permission:sales.promotions.view')->name('sales.promotions.analytics');
    });

    /*
    |--------------------------------------------------------------------------
    | IFRS 15 Revenue Recognition
    |--------------------------------------------------------------------------
    */
    Route::prefix('revenue-contracts')->group(function (): void {
        Route::get('/', [RevenueRecognitionController::class, 'index'])
            ->middleware('check.permission:sales.revenue_contracts.view')
            ->name('sales.revenue_contracts.index');
        Route::post('/', [RevenueRecognitionController::class, 'store'])
            ->middleware('check.permission:sales.revenue_contracts.create')
            ->name('sales.revenue_contracts.store');
        Route::get('/deferred-balance', [RevenueRecognitionController::class, 'deferredBalance'])
            ->middleware('check.permission:sales.revenue_contracts.view')
            ->name('sales.revenue_contracts.deferred_balance');
        Route::get('/{revenueContract}', [RevenueRecognitionController::class, 'show'])
            ->middleware('check.permission:sales.revenue_contracts.view')
            ->name('sales.revenue_contracts.show');
        Route::put('/{revenueContract}', [RevenueRecognitionController::class, 'update'])
            ->middleware('check.permission:sales.revenue_contracts.edit')
            ->name('sales.revenue_contracts.update');
        Route::post('/{revenueContract}/allocate', [RevenueRecognitionController::class, 'allocate'])
            ->middleware('check.permission:sales.revenue_contracts.edit')
            ->name('sales.revenue_contracts.allocate');
    });

    Route::prefix('performance-obligations')->group(function (): void {
        Route::post('/{performanceObligation}/recognize', [RevenueRecognitionController::class, 'recognize'])
            ->middleware('check.permission:sales.revenue_contracts.recognize')
            ->name('sales.performance_obligations.recognize');
    });

    /*
    |--------------------------------------------------------------------------
    | CPQ — Configure, Price, Quote (SD-CPQ)
    |--------------------------------------------------------------------------
    */
    Route::prefix('cpq')->name('sales.cpq.')->group(function (): void {
        Route::get('/products', [CpqController::class, 'products'])->name('products');
        Route::post('/products', [CpqController::class, 'storeProduct'])->name('products.store');
        Route::get('/products/{id}', [CpqController::class, 'showProduct'])->name('products.show');
        Route::put('/products/{id}', [CpqController::class, 'updateProduct'])->name('products.update');
        Route::get('/products/{productId}/option-groups', [CpqController::class, 'optionGroups'])->name('option-groups');
        Route::post('/products/{productId}/option-groups', [CpqController::class, 'storeOptionGroup'])->name('option-groups.store');
        Route::post('/option-groups/{groupId}/options', [CpqController::class, 'storeOption'])->name('options.store');
        Route::get('/products/{productId}/pricing-rules', [CpqController::class, 'pricingRules'])->name('pricing-rules');
        Route::post('/products/{productId}/pricing-rules', [CpqController::class, 'storePricingRule'])->name('pricing-rules.store');
        Route::get('/products/{productId}/constraint-rules', [CpqController::class, 'constraintRules'])->name('constraint-rules');
        Route::post('/products/{productId}/constraint-rules', [CpqController::class, 'storeConstraintRule'])->name('constraint-rules.store');
        Route::post('/configure', [CpqController::class, 'configure'])->name('configure');
        Route::get('/configurations', [CpqController::class, 'configurations'])->name('configurations');
        Route::post('/configurations', [CpqController::class, 'saveConfiguration'])->name('configurations.store');
        Route::get('/configurations/{id}', [CpqController::class, 'showConfiguration'])->name('configurations.show');
        Route::post('/configurations/{id}/convert', [CpqController::class, 'convertToQuotation'])->name('configurations.convert');
    });
});

// Sales Order Costing (SD-CO)
Route::middleware(['auth:api'])->prefix('order-costing')->name('sd.order-costing.')->group(function () {
    Route::get('/', [SalesOrderCostingController::class, 'index'])->name('index');
    Route::post('/', [SalesOrderCostingController::class, 'store'])->name('store');
    Route::get('/{id}', [SalesOrderCostingController::class, 'show'])->name('show');
    Route::put('/{id}', [SalesOrderCostingController::class, 'update'])->name('update');
    Route::post('/{id}/items', [SalesOrderCostingController::class, 'addItem'])->name('items.add');
    Route::post('/{id}/release', [SalesOrderCostingController::class, 'release'])->name('release');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Billing Plans
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('billing-plans')->name('sd.billing-plans.')->group(function (): void {
    Route::get('/', [BillingPlanController::class, 'index'])->name('index');
    Route::post('/', [BillingPlanController::class, 'store'])->name('store');
    Route::get('/due-items', [BillingPlanController::class, 'dueItems'])->name('due-items');
    Route::get('/{id}', [BillingPlanController::class, 'show'])->name('show');
    Route::put('/{id}', [BillingPlanController::class, 'update'])->name('update');
    Route::delete('/{id}', [BillingPlanController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/items', [BillingPlanController::class, 'addItem'])->name('items.add');
    Route::put('/{id}/items/{itemId}', [BillingPlanController::class, 'updateItem'])->name('items.update');
    Route::post('/{id}/items/{itemId}/bill', [BillingPlanController::class, 'billItem'])->name('items.bill');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Backorders
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('backorders')->name('sd.backorders.')->group(function (): void {
    Route::get('/', [BackorderController::class, 'index'])->name('index');
    Route::post('/', [BackorderController::class, 'store'])->name('store');
    Route::get('/report', [BackorderController::class, 'report'])->name('report');
    Route::get('/{id}', [BackorderController::class, 'show'])->name('show');
    Route::post('/{id}/reschedule', [BackorderController::class, 'reschedule'])->name('reschedule');
    Route::post('/{id}/fulfill', [BackorderController::class, 'fulfill'])->name('fulfill');
    Route::post('/{id}/cancel', [BackorderController::class, 'cancel'])->name('cancel');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Third-Party Orders (Drop Shipment)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('third-party-orders')->name('sd.tpo.')->group(function (): void {
    Route::get('/', [ThirdPartyOrderController::class, 'index'])->name('index');
    Route::post('/', [ThirdPartyOrderController::class, 'store'])->name('store');
    Route::get('/{id}', [ThirdPartyOrderController::class, 'show'])->name('show');
    Route::put('/{id}', [ThirdPartyOrderController::class, 'update'])->name('update');
    Route::post('/{id}/create-po', [ThirdPartyOrderController::class, 'createPO'])->name('create-po');
    Route::post('/{id}/confirm-shipment', [ThirdPartyOrderController::class, 'confirmShipment'])->name('confirm-shipment');
    Route::post('/{id}/confirm-delivery', [ThirdPartyOrderController::class, 'confirmDelivery'])->name('confirm-delivery');
    Route::post('/{id}/cancel', [ThirdPartyOrderController::class, 'cancel'])->name('cancel');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Shipping Zones
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('shipping-zones')->name('sd.shipping-zones.')->group(function (): void {
    Route::get('/', [ShippingRouteController::class, 'zoneIndex'])->name('index');
    Route::post('/', [ShippingRouteController::class, 'zoneStore'])->name('store');
    Route::get('/{id}', [ShippingRouteController::class, 'zoneShow'])->name('show');
    Route::put('/{id}', [ShippingRouteController::class, 'zoneUpdate'])->name('update');
    Route::delete('/{id}', [ShippingRouteController::class, 'zoneDestroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Shipping Routes & Route Determination
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('shipping-routes')->name('sd.shipping-routes.')->group(function (): void {
    Route::get('/', [ShippingRouteController::class, 'routeIndex'])->name('index');
    Route::post('/', [ShippingRouteController::class, 'routeStore'])->name('store');
    Route::get('/{id}', [ShippingRouteController::class, 'routeShow'])->name('show');
    Route::put('/{id}', [ShippingRouteController::class, 'routeUpdate'])->name('update');
    Route::delete('/{id}', [ShippingRouteController::class, 'routeDestroy'])->name('destroy');
    Route::post('/determine', [ShippingRouteController::class, 'determineRoute'])->name('determine');
    Route::post('/determine-for-order/{orderId}', [ShippingRouteController::class, 'determineForOrder'])->name('determine-for-order');
});

/*
|--------------------------------------------------------------------------
| SD Advanced: Handling Units (Packing)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('handling-units')->name('sd.handling-units.')->group(function (): void {
    Route::get('/', [HandlingUnitController::class, 'index'])->name('index');
    Route::post('/', [HandlingUnitController::class, 'store'])->name('store');
    Route::get('/{id}', [HandlingUnitController::class, 'show'])->name('show');
    Route::put('/{id}', [HandlingUnitController::class, 'update'])->name('update');
    Route::delete('/{id}', [HandlingUnitController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/items', [HandlingUnitController::class, 'addItem'])->name('items.add');
    Route::delete('/{id}/items/{itemId}', [HandlingUnitController::class, 'removeItem'])->name('items.remove');
    Route::post('/{id}/seal', [HandlingUnitController::class, 'seal'])->name('seal');
    Route::get('/packing-list/{shipmentId}', [HandlingUnitController::class, 'packingList'])->name('packing-list');
});


/*
|--------------------------------------------------------------------------
| VF04 — Billing Due List
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('billing-due-list')->name('sd.billing-due-list.')->group(function (): void {
    Route::get('/', [BillingDueListController::class, 'index'])->name('index');
    Route::post('/collective-run', [BillingDueListController::class, 'collectiveRun'])->name('collective-run');
    Route::post('/{item}/bill', [BillingDueListController::class, 'billItem'])->name('bill-item');
});

/*
|--------------------------------------------------------------------------
| Rebate Management & Settlement — SAP SD BO01/VB01
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('rebates')->name('sd.rebates.')->group(function (): void {
    Route::get('/', [RebateController::class, 'index'])->name('index');
    Route::post('/', [RebateController::class, 'store'])->name('store');
    Route::get('/{rebate}', [RebateController::class, 'show'])->name('show');
    Route::put('/{rebate}', [RebateController::class, 'update'])->name('update');
    Route::get('/{rebate}/balance', [RebateController::class, 'balance'])->name('balance');
    Route::post('/{rebate}/settle', [RebateController::class, 'settle'])->name('settle');
    Route::post('/period-end-run', [RebateController::class, 'periodEndRun'])->name('period-end-run');
});
