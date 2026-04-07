<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use Illuminate\Support\Facades\DB;

class OpenItemClearingService
{
    /**
     * Clear AR open items: apply a payment against one or more invoices (FIFO by due date).
     *
     * Returns ['cleared' => [...], 'partial' => [...], 'remaining_amount' => string].
     */
    public function clearArItems(int $organizationId, int $paymentId): array
    {
        return DB::transaction(function () use ($organizationId, $paymentId): array {
            $payment = PaymentReceived::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $remaining = (string) $payment->amount;
            $cleared   = [];
            $partial   = [];

            // FIFO: oldest due_date first
            $openInvoices = Invoice::where('organization_id', $organizationId)
                ->where('customer_id', $payment->customer_id)
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->where(DB::raw('total - amount_paid'), '>', 0)
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            foreach ($openInvoices as $invoice) {
                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }

                $outstanding = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 4);

                if (bccomp($remaining, $outstanding, 4) >= 0) {
                    // Fully clear this invoice
                    $remaining = bcsub($remaining, $outstanding, 4);
                    $invoice->update([
                        'amount_paid' => $invoice->total,
                        'amount_due'  => '0.0000',
                        'status'      => Invoice::STATUS_PAID,
                    ]);
                    $cleared[] = ['invoice_id' => $invoice->id, 'amount_applied' => $outstanding];
                } else {
                    // Partially clear
                    $applied = $remaining;
                    $invoice->update([
                        'amount_paid' => bcadd((string) $invoice->amount_paid, $applied, 4),
                        'amount_due'  => bcsub((string) $invoice->total, bcadd((string) $invoice->amount_paid, $applied, 4), 4),
                        'status'      => Invoice::STATUS_PARTIAL,
                    ]);
                    $partial[]  = ['invoice_id' => $invoice->id, 'amount_applied' => $applied];
                    $remaining  = '0.0000';
                }
            }

            return [
                'payment_id'       => $paymentId,
                'cleared'          => $cleared,
                'partial'          => $partial,
                'remaining_amount' => $remaining,
            ];
        });
    }

    /**
     * Clear AP open items: apply a payment against bills (FIFO by due date).
     */
    public function clearApItems(int $organizationId, int $paymentId): array
    {
        return DB::transaction(function () use ($organizationId, $paymentId): array {
            $payment = PaymentMade::where('organization_id', $organizationId)
                ->lockForUpdate()
                ->findOrFail($paymentId);

            $remaining = (string) $payment->amount;
            $cleared   = [];
            $partial   = [];

            $openBills = Bill::where('organization_id', $organizationId)
                ->where('supplier_id', $payment->supplier_id)
                ->whereIn('status', ['approved', 'partial', 'overdue'])
                ->where(DB::raw('total - amount_paid'), '>', 0)
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            foreach ($openBills as $bill) {
                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }

                $outstanding = bcsub((string) $bill->total, (string) $bill->amount_paid, 4);

                if (bccomp($remaining, $outstanding, 4) >= 0) {
                    $remaining = bcsub($remaining, $outstanding, 4);
                    $bill->update([
                        'amount_paid' => $bill->total,
                        'amount_due'  => '0.0000',
                        'status'      => Bill::STATUS_PAID,
                    ]);
                    $cleared[] = ['bill_id' => $bill->id, 'amount_applied' => $outstanding];
                } else {
                    $bill->update([
                        'amount_paid' => bcadd((string) $bill->amount_paid, $remaining, 4),
                        'amount_due'  => bcsub((string) $bill->total, bcadd((string) $bill->amount_paid, $remaining, 4), 4),
                        'status'      => Bill::STATUS_PARTIAL,
                    ]);
                    $partial[]  = ['bill_id' => $bill->id, 'amount_applied' => $remaining];
                    $remaining  = '0.0000';
                }
            }

            return [
                'payment_id'       => $paymentId,
                'cleared'          => $cleared,
                'partial'          => $partial,
                'remaining_amount' => $remaining,
            ];
        });
    }

    /**
     * List all open AR items for a customer.
     */
    public function getArOpenItems(int $organizationId, int $customerId): array
    {
        $items = Invoice::where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where(DB::raw('total - amount_paid'), '>', 0)
            ->orderBy('due_date')
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'total', 'amount_paid', 'status'])
            ->map(static fn (Invoice $i): array => array_merge(
                $i->toArray(),
                ['outstanding' => bcsub((string) $i->total, (string) $i->amount_paid, 4)]
            ));

        return [
            'customer_id'       => $customerId,
            'open_items'        => $items,
            'total_outstanding' => $items->sum('outstanding'),
        ];
    }

    /**
     * List all open AP items for a vendor.
     */
    public function getApOpenItems(int $organizationId, int $supplierId): array
    {
        $items = Bill::where('organization_id', $organizationId)
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['approved', 'partial', 'overdue'])
            ->where(DB::raw('total - amount_paid'), '>', 0)
            ->orderBy('due_date')
            ->get(['id', 'bill_number', 'bill_date', 'due_date', 'total', 'amount_paid', 'status'])
            ->map(static fn (Bill $b): array => array_merge(
                $b->toArray(),
                ['outstanding' => bcsub((string) $b->total, (string) $b->amount_paid, 4)]
            ));

        return [
            'supplier_id'       => $supplierId,
            'open_items'        => $items,
            'total_outstanding' => $items->sum('outstanding'),
        ];
    }
}
