<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Accounting\JournalEntryPosted;
use App\Events\CRM\LeadConverted;
use App\Events\CRM\OpportunityWon;
use App\Events\HR\LeaveRequestApproved;
use App\Events\HR\LeaveRequestSubmitted;
use App\Events\HR\PayslipGenerated;
use App\Events\Inventory\LowStockAlert;
use App\Events\Inventory\StockLevelChanged;
use App\Events\Manufacturing\ProductionRecorded;
use App\Events\Manufacturing\WorkOrderCompleted;
use App\Events\Manufacturing\WorkOrderStarted;
use App\Events\Purchase\BillApproved;
use App\Events\Purchase\PurchaseOrderReceived;
use App\Events\Sales\InvoicePaid;
use App\Events\Sales\InvoicePosted;
use App\Events\Sales\PaymentReceived;
use App\Listeners\CRM\CreateOpportunityFromWonListener;
use App\Listeners\CRM\NotifyLeadConvertedListener;
use App\Listeners\HR\NotifyEmployeeLeaveApproval;
use App\Listeners\HR\NotifyEmployeePayslipListener;
use App\Listeners\HR\NotifyLeaveApprover;
use App\Listeners\Inventory\CheckLowStockListener;
use App\Listeners\Inventory\SendLowStockNotification;
use App\Listeners\Manufacturing\NotifyWorkOrderStartedListener;
use App\Listeners\Manufacturing\UpdateInventoryOnProductionListener;
use App\Listeners\Manufacturing\UpdateProductCostListener;
use App\Listeners\Purchase\NotifyBillApprovedListener;
use App\Listeners\Purchase\UpdateStockOnReceiptListener;
use App\Listeners\Sales\NotifyInvoicePaidListener;
use App\Listeners\Core\MonitorFailedJobListener;
use App\Listeners\Accounting\FanOutToParallelLedgers;
use App\Listeners\Sales\PostCopaOnInvoicePostedListener;
use App\Listeners\Sales\UpdateCustomerBalanceOnInvoicePostedListener;
use App\Listeners\Sales\UpdateCustomerBalanceOnPaymentReceivedListener;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Inventory Events
        StockLevelChanged::class => [
            CheckLowStockListener::class,
        ],

        LowStockAlert::class => [
            SendLowStockNotification::class,
        ],

        // Accounting Events
        JournalEntryPosted::class => [
            FanOutToParallelLedgers::class,
        ],

        // Sales Events
        InvoicePosted::class => [
            UpdateCustomerBalanceOnInvoicePostedListener::class,
            PostCopaOnInvoicePostedListener::class,
        ],

        InvoicePaid::class => [
            NotifyInvoicePaidListener::class,
        ],

        PaymentReceived::class => [
            UpdateCustomerBalanceOnPaymentReceivedListener::class,
        ],

        // Purchase Events
        BillApproved::class => [
            NotifyBillApprovedListener::class,
        ],

        PurchaseOrderReceived::class => [
            UpdateStockOnReceiptListener::class,
        ],

        // HR Events
        LeaveRequestSubmitted::class => [
            NotifyLeaveApprover::class,
        ],

        LeaveRequestApproved::class => [
            NotifyEmployeeLeaveApproval::class,
        ],

        PayslipGenerated::class => [
            NotifyEmployeePayslipListener::class,
        ],

        // Manufacturing Events
        WorkOrderStarted::class => [
            NotifyWorkOrderStartedListener::class,
        ],

        WorkOrderCompleted::class => [
            UpdateProductCostListener::class,
        ],

        ProductionRecorded::class => [
            UpdateInventoryOnProductionListener::class,
        ],

        // CRM Events
        LeadConverted::class => [
            NotifyLeadConvertedListener::class,
        ],

        OpportunityWon::class => [
            CreateOpportunityFromWonListener::class,
        ],

        // System Events
        JobFailed::class => [
            MonitorFailedJobListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
