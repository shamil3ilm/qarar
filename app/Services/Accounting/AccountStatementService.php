<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Services\Core\EmailService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AccountStatementService
{
    public function __construct(
        private EmailService $emailService,
    ) {}

    // -------------------------------------------------------------------------
    // Customer Statement
    // -------------------------------------------------------------------------

    /**
     * Generate a customer account statement for a date range.
     *
     * @return array{
     *   contact: Contact,
     *   period: array{from: string, to: string},
     *   opening_balance: float,
     *   transactions: array,
     *   closing_balance: float,
     *   overdue_amount: float,
     *   currency: string
     * }
     */
    public function generateCustomerStatement(
        int $contactId,
        string $fromDate,
        string $toDate,
        int $orgId,
    ): array {
        $contact = Contact::where('organization_id', $orgId)->findOrFail($contactId);

        // Opening balance: sum of invoices issued before fromDate minus payments received before fromDate
        $invoicesBeforePeriod = Invoice::where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->whereDate('invoice_date', '<', $fromDate)
            ->whereNotIn('status', [Invoice::STATUS_VOIDED])
            ->sum('amount_due');

        $paymentsBeforePeriod = PaymentReceived::where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->whereDate('payment_date', '<', $fromDate)
            ->whereNotIn('status', ['voided'])
            ->sum('amount');

        $openingBalance = (float) $invoicesBeforePeriod - (float) $paymentsBeforePeriod;

        // Transactions within the period
        $transactions  = [];
        $runningBalance = $openingBalance;

        // Invoices in period
        $invoices = Invoice::where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->whereNotIn('status', [Invoice::STATUS_VOIDED])
            ->orderBy('invoice_date')
            ->get();

        foreach ($invoices as $inv) {
            $runningBalance += (float) $inv->total;
            $transactions[] = [
                'date'            => $inv->invoice_date->toDateString(),
                'type'            => 'invoice',
                'reference'       => $inv->invoice_number,
                'description'     => "Invoice #{$inv->invoice_number}",
                'debit'           => (float) $inv->total,
                'credit'          => 0.0,
                'running_balance' => round($runningBalance, 4),
                'status'          => $inv->status,
            ];
        }

        // Payments received in period
        $payments = PaymentReceived::where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->whereNotIn('status', ['voided'])
            ->orderBy('payment_date')
            ->get();

        foreach ($payments as $pmt) {
            $runningBalance -= (float) $pmt->amount;
            $transactions[] = [
                'date'            => $pmt->payment_date->toDateString(),
                'type'            => 'payment',
                'reference'       => $pmt->payment_number ?? $pmt->reference ?? '',
                'description'     => "Payment received",
                'debit'           => 0.0,
                'credit'          => (float) $pmt->amount,
                'running_balance' => round($runningBalance, 4),
                'status'          => $pmt->status,
            ];
        }

        // Sort combined transactions by date
        usort($transactions, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        // Recalculate running balance after sorting
        $runningBalance = $openingBalance;
        foreach ($transactions as &$txn) {
            $runningBalance += $txn['debit'] - $txn['credit'];
            $txn['running_balance'] = round($runningBalance, 4);
        }
        unset($txn);

        // Overdue amount: all unpaid/partially-paid invoices past due date
        $overdueAmount = (float) Invoice::where('organization_id', $orgId)
            ->where('contact_id', $contactId)
            ->whereIn('status', [Invoice::STATUS_PARTIAL, 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->sum('amount_due');

        return [
            'contact'         => $contact,
            'period'          => ['from' => $fromDate, 'to' => $toDate],
            'opening_balance' => round($openingBalance, 4),
            'transactions'    => $transactions,
            'closing_balance' => round($runningBalance, 4),
            'overdue_amount'  => round($overdueAmount, 4),
            'currency'        => $contact->currency_code ?? 'SAR',
        ];
    }

    // -------------------------------------------------------------------------
    // Vendor Statement
    // -------------------------------------------------------------------------

    /**
     * Generate a vendor account statement for a date range.
     */
    public function generateVendorStatement(
        int $contactId,
        string $fromDate,
        string $toDate,
        int $orgId,
    ): array {
        $contact = Contact::where('organization_id', $orgId)->findOrFail($contactId);

        // Opening balance: bills issued before period minus payments made before period
        $billsBeforePeriod = Bill::where('organization_id', $orgId)
            ->where('supplier_id', $contactId)
            ->whereDate('bill_date', '<', $fromDate)
            ->whereNotIn('status', [Bill::STATUS_VOIDED])
            ->sum('amount_due');

        $paymentsBeforePeriod = PaymentMade::where('organization_id', $orgId)
            ->where('supplier_id', $contactId)
            ->whereDate('payment_date', '<', $fromDate)
            ->whereNotIn('status', ['voided'])
            ->sum('amount');

        $openingBalance = (float) $billsBeforePeriod - (float) $paymentsBeforePeriod;

        $transactions   = [];
        $runningBalance = $openingBalance;

        // Bills in period
        $bills = Bill::where('organization_id', $orgId)
            ->where('supplier_id', $contactId)
            ->whereBetween('bill_date', [$fromDate, $toDate])
            ->whereNotIn('status', [Bill::STATUS_VOIDED])
            ->orderBy('bill_date')
            ->get();

        foreach ($bills as $bill) {
            $runningBalance += (float) $bill->total;
            $transactions[] = [
                'date'            => $bill->bill_date->toDateString(),
                'type'            => 'bill',
                'reference'       => $bill->bill_number,
                'description'     => "Bill #{$bill->bill_number}",
                'debit'           => (float) $bill->total,
                'credit'          => 0.0,
                'running_balance' => round($runningBalance, 4),
                'status'          => $bill->status,
            ];
        }

        // Payments made in period
        $payments = PaymentMade::where('organization_id', $orgId)
            ->where('supplier_id', $contactId)
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->whereNotIn('status', ['voided'])
            ->orderBy('payment_date')
            ->get();

        foreach ($payments as $pmt) {
            $runningBalance -= (float) $pmt->amount;
            $transactions[] = [
                'date'            => $pmt->payment_date->toDateString(),
                'type'            => 'payment',
                'reference'       => $pmt->payment_number ?? $pmt->reference ?? '',
                'description'     => "Payment made",
                'debit'           => 0.0,
                'credit'          => (float) $pmt->amount,
                'running_balance' => round($runningBalance, 4),
                'status'          => $pmt->status,
            ];
        }

        usort($transactions, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        $runningBalance = $openingBalance;
        foreach ($transactions as &$txn) {
            $runningBalance += $txn['debit'] - $txn['credit'];
            $txn['running_balance'] = round($runningBalance, 4);
        }
        unset($txn);

        $overdueAmount = (float) Bill::where('organization_id', $orgId)
            ->where('supplier_id', $contactId)
            ->whereIn('status', [Bill::STATUS_PARTIAL, 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->sum('amount_due');

        return [
            'contact'         => $contact,
            'period'          => ['from' => $fromDate, 'to' => $toDate],
            'opening_balance' => round($openingBalance, 4),
            'transactions'    => $transactions,
            'closing_balance' => round($runningBalance, 4),
            'overdue_amount'  => round($overdueAmount, 4),
            'currency'        => $contact->currency_code ?? 'SAR',
        ];
    }

    // -------------------------------------------------------------------------
    // Email
    // -------------------------------------------------------------------------

    /**
     * Send a statement to an email address as plain-text data (no PDF for now).
     */
    public function sendStatementByEmail(array $statementData, string $email): void
    {
        try {
            $this->emailService->sendTemplate(
                'account_statement',
                $email,
                $statementData,
            );
        } catch (\Throwable $e) {
            Log::warning('Account statement email failed', [
                'email'   => $email,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Open Items
    // -------------------------------------------------------------------------

    /**
     * Return all open (unpaid / partially-paid) invoices or bills with age buckets.
     *
     * Age buckets: current, 1-30, 31-60, 61-90, 90+
     *
     * @return array{items: array, aging_summary: array}
     */
    public function getOpenItems(int $contactId, string $type, int $orgId): array
    {
        $today = Carbon::today();
        $items = [];

        if ($type === 'customer') {
            $records = Invoice::where('organization_id', $orgId)
                ->where('contact_id', $contactId)
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, 'overdue'])
                ->orderBy('due_date')
                ->get();

            foreach ($records as $rec) {
                $items[] = $this->buildAgingItem(
                    $today,
                    $rec->due_date,
                    $rec->invoice_number,
                    'invoice',
                    (float) $rec->amount_due,
                    $rec->invoice_date->toDateString(),
                );
            }
        } else {
            $records = Bill::where('organization_id', $orgId)
                ->where('supplier_id', $contactId)
                ->whereIn('status', [Bill::STATUS_PENDING, Bill::STATUS_PARTIAL, 'overdue'])
                ->orderBy('due_date')
                ->get();

            foreach ($records as $rec) {
                $items[] = $this->buildAgingItem(
                    $today,
                    $rec->due_date,
                    $rec->bill_number,
                    'bill',
                    (float) $rec->amount_due,
                    $rec->bill_date->toDateString(),
                );
            }
        }

        // Aging summary
        $summary = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        foreach ($items as $item) {
            $bucket = $item['age_bucket'];
            $summary[$bucket] = round($summary[$bucket] + $item['balance_due'], 4);
        }

        return ['items' => $items, 'aging_summary' => $summary];
    }

    /**
     * Record that a statement was confirmed by the customer/vendor (simple activity log entry).
     */
    public function confirmReconciliation(
        int $contactId,
        string $type,
        string $confirmedDate,
        int $orgId,
    ): void {
        Log::info('Statement reconciliation confirmed', [
            'organization_id' => $orgId,
            'contact_id'      => $contactId,
            'type'            => $type,
            'confirmed_date'  => $confirmedDate,
            'confirmed_by'    => auth()->id(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildAgingItem(
        Carbon $today,
        mixed $dueDate,
        string $reference,
        string $type,
        float $balanceDue,
        string $transactionDate,
    ): array {
        $due     = $dueDate instanceof Carbon ? $dueDate : Carbon::parse($dueDate);
        $daysOverdue = $today->diffInDays($due, false); // negative = overdue

        $ageBucket = match (true) {
            $daysOverdue >= 0  => 'current',
            $daysOverdue >= -30 => '1_30',
            $daysOverdue >= -60 => '31_60',
            $daysOverdue >= -90 => '61_90',
            default             => '90_plus',
        };

        return [
            'reference'        => $reference,
            'type'             => $type,
            'transaction_date' => $transactionDate,
            'due_date'         => $due->toDateString(),
            'days_overdue'     => max(0, (int) abs($daysOverdue)),
            'balance_due'      => $balanceDue,
            'age_bucket'       => $ageBucket,
        ];
    }
}
