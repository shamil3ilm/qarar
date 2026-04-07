<?php

declare(strict_types=1);

namespace App\Listeners\Sales;

use App\Events\Sales\InvoicePaid;
use App\Models\User;
use App\Services\Core\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyInvoicePaidListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;
        $customer = $invoice->customer;

        if (!$customer) {
            return;
        }

        // Update customer outstanding balance
        $customer->updateOutstandingBalance();

        // Notify the invoice creator and finance team
        $recipients = User::withoutGlobalScopes()
            ->where('organization_id', $invoice->organization_id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['accountant', 'admin', 'manager']))
            ->pluck('id')
            ->toArray();

        if ($invoice->created_by) {
            $recipients[] = $invoice->created_by;
        }

        foreach (array_unique($recipients) as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            $this->notificationService->send(
                $user,
                'invoice_paid',
                "Invoice #{$invoice->invoice_number} has been fully paid",
                "Invoice #{$invoice->invoice_number} has been fully paid",
                null,
                null,
                null,
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount_paid' => $event->amountPaid,
                    'customer_name' => $customer->name,
                ]
            );
        }
    }
}
