<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Core\UserEvent;
use App\Models\Sales\Contact;
use App\Models\Sales\CustomerCredit;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentAllocation;
use App\Models\Sales\PaymentReceived;
use App\Jobs\RunFraudChecksJob;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Core\UserEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private NumberGeneratorService $numberGenerator,
        private UserEventService $userEventService
    ) {}

    /**
     * Create a new payment.
     */
    public function create(array $data, array $allocations = []): PaymentReceived
    {
        $payment = DB::transaction(function () use ($data, $allocations) {
            // Generate payment number
            if (empty($data['payment_number'])) {
                $data['payment_number'] = $this->numberGenerator->generate('PAY');
            }

            // Calculate base amount
            $data['base_amount'] = bcmul(
                (string) $data['amount'],
                (string) ($data['exchange_rate'] ?? 1),
                4
            );

            $payment = PaymentReceived::create($data);

            // Allocate to invoices
            $totalAllocated = 0;
            foreach ($allocations as $allocation) {
                $invoice = Invoice::findOrFail($allocation['invoice_id']);
                $amount = bccomp((string) $allocation['amount'], (string) $invoice->amount_due, 4) > 0
                    ? (string) $invoice->amount_due
                    : (string) $allocation['amount'];

                if ($amount > 0) {
                    try {
                        $this->allocate($payment, $invoice, $amount);
                        $totalAllocated = bcadd((string) $totalAllocated, (string) $amount, 4);
                    } catch (\InvalidArgumentException $e) {
                        \Illuminate\Support\Facades\Log::warning('Payment allocation skipped: ' . $e->getMessage());
                    }
                }
            }

            // Create customer credit for unallocated amount
            $unallocated = bcsub((string) $payment->amount, (string) $totalAllocated, 4);
            if (bccomp($unallocated, '0', 4) > 0) {
                try {
                    $this->createCustomerCredit($payment, (float) $unallocated);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Customer credit creation skipped: ' . $e->getMessage());
                }
            }

            return $payment->load('allocations.invoice', 'customer');
        });

        try {
            $this->userEventService->track(
                UserEvent::PAYMENT_RECEIVED,
                ['payment_id' => $payment->id, 'amount' => $payment->amount, 'invoice_id' => $payment->allocations->first()?->invoice_id],
                auth('api')->id(),
                $payment->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::PAYMENT_RECEIVED, 'error' => $e->getMessage()]);
        }

        // Track INVOICE_PAID for any invoices that became fully paid during this transaction
        foreach ($payment->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if ($invoice && $invoice->status === Invoice::STATUS_PAID) {
                try {
                    $this->userEventService->track(
                        UserEvent::INVOICE_PAID,
                        ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'total' => $invoice->total],
                        auth('api')->id(),
                        $invoice->organization_id,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Event tracking failed', ['event' => UserEvent::INVOICE_PAID, 'error' => $e->getMessage()]);
                }
            }
        }

        // Dispatch fraud check asynchronously — non-blocking
        try {
            RunFraudChecksJob::dispatch(
                'payment',
                $payment->id,
                [
                    'uuid'       => $payment->uuid ?? null,
                    'amount'     => (float) $payment->amount,
                    'contact_id' => $payment->customer_id,
                    'currency'   => $payment->currency_code,
                ],
                $payment->organization_id,
                auth('api')->id(),
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Fraud check dispatch failed for payment', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }

        return $payment;
    }

    /**
     * Complete/confirm a payment.
     */
    public function complete(PaymentReceived $payment): PaymentReceived
    {
        if ($payment->status !== PaymentReceived::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending payments can be completed.');
        }

        return DB::transaction(function () use ($payment) {
            $journalId = null;

            // Create journal entry — only skip if accounts are not yet configured
            // (e.g. fresh org setup). Re-throw all other failures so the payment
            // is not completed without a corresponding ledger entry.
            try {
                $journal = $this->createJournalEntry($payment);
                $journalId = $journal->id;
            } catch (\InvalidArgumentException $e) {
                Log::error('Payment journal entry skipped — missing account config', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $payment->update([
                'status' => PaymentReceived::STATUS_COMPLETED,
                'journal_entry_id' => $journalId,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Void a payment.
     */
    public function void(PaymentReceived $payment, string $reason = ''): PaymentReceived
    {
        if ($payment->status === PaymentReceived::STATUS_VOIDED) {
            throw new \InvalidArgumentException('Payment is already voided.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            // Reverse allocations
            foreach ($payment->allocations as $allocation) {
                $invoice = $allocation->invoice;
                $invoice->amount_paid = bcsub((string) $invoice->amount_paid, (string) $allocation->amount, 4);
                $invoice->amount_due = bcadd((string) $invoice->amount_due, (string) $allocation->amount, 4);

                // Reset invoice status
                if (bccomp((string) $invoice->amount_paid, '0', 4) <= 0) {
                    $invoice->status = $invoice->isOverdue() ? Invoice::STATUS_OVERDUE : Invoice::STATUS_SENT;
                } else {
                    $invoice->status = Invoice::STATUS_PARTIAL;
                }

                $invoice->save();
            }

            // Delete allocations
            $payment->allocations()->delete();

            // Reverse journal entry
            if ($payment->journal_entry_id && $payment->journalEntry) {
                try {
                    $this->journalService->void($payment->journalEntry, $reason);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Payment journal void skipped: ' . $e->getMessage());
                }
            }

            // Void customer credit if created
            try {
                CustomerCredit::where('source_type', CustomerCredit::SOURCE_OVERPAYMENT)
                    ->where('source_id', $payment->id)
                    ->update(['is_active' => false, 'remaining_amount' => 0]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Customer credit void skipped: ' . $e->getMessage());
            }

            $payment->update([
                'status' => PaymentReceived::STATUS_VOIDED,
                'notes' => $payment->notes . "\n\nVoided: " . $reason,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Record a bounced cheque.
     */
    public function recordBounce(PaymentReceived $payment, string $reason = ''): PaymentReceived
    {
        if ($payment->payment_method !== PaymentReceived::METHOD_CHEQUE) {
            throw new \InvalidArgumentException('Only cheque payments can bounce.');
        }

        if ($payment->status !== PaymentReceived::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Only completed payments can bounce.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            // Reverse same as void but with different status
            foreach ($payment->allocations as $allocation) {
                $invoice = $allocation->invoice;
                $invoice->amount_paid = bcsub((string) $invoice->amount_paid, (string) $allocation->amount, 4);
                $invoice->amount_due = bcadd((string) $invoice->amount_due, (string) $allocation->amount, 4);

                if (bccomp((string) $invoice->amount_paid, '0', 4) <= 0) {
                    $invoice->status = $invoice->isOverdue() ? Invoice::STATUS_OVERDUE : Invoice::STATUS_SENT;
                } else {
                    $invoice->status = Invoice::STATUS_PARTIAL;
                }

                $invoice->save();
            }

            // Create reversal journal entry
            if ($payment->journal_entry_id && $payment->journalEntry) {
                try {
                    $this->journalService->reverse($payment->journalEntry, "Cheque bounced: {$reason}");
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Payment journal reverse skipped: ' . $e->getMessage());
                }
            }

            // Reverse any customer credit created from this payment (overpayment credit)
            try {
                CustomerCredit::where('source_type', CustomerCredit::SOURCE_OVERPAYMENT)
                    ->where('source_id', $payment->id)
                    ->update(['is_active' => false, 'remaining_amount' => 0]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Customer credit reversal on bounce skipped: ' . $e->getMessage());
            }

            $payment->update([
                'status' => PaymentReceived::STATUS_BOUNCED,
                'notes' => $payment->notes . "\n\nBounced: " . $reason,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Allocate payment to an invoice.
     */
    public function allocate(PaymentReceived $payment, Invoice $invoice, string|float|int $amount): PaymentAllocation
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive.');
        }

        if (in_array($invoice->status, [Invoice::STATUS_VOIDED, Invoice::STATUS_DRAFT], true)) {
            throw new \InvalidArgumentException(
                "Cannot allocate payment to invoice #{$invoice->invoice_number} with status '{$invoice->status}'."
            );
        }

        $available = $payment->getUnallocatedAmount();
        if ($amount > $available) {
            throw new \InvalidArgumentException("Cannot allocate {$amount}. Only {$available} available.");
        }

        if ($amount > $invoice->amount_due) {
            throw new \InvalidArgumentException("Cannot allocate {$amount}. Invoice only has {$invoice->amount_due} due.");
        }

        // Check currency match (skip if either currency is not set)
        if ($payment->currency_code && $invoice->currency_code
            && $payment->currency_code !== $invoice->currency_code) {
            \Illuminate\Support\Facades\Log::warning("Payment currency ({$payment->currency_code}) differs from invoice currency ({$invoice->currency_code}).");
        }

        return DB::transaction(function () use ($payment, $invoice, $amount) {
            // Re-fetch payment with a pessimistic lock to prevent concurrent over-allocation
            $payment = PaymentReceived::lockForUpdate()->findOrFail($payment->id);

            // Re-validate unallocated amount inside the payment lock
            $available = $payment->getUnallocatedAmount();
            if ($amount > $available) {
                throw new \InvalidArgumentException("Cannot allocate {$amount}. Only {$available} available.");
            }

            // Re-fetch with a pessimistic lock to prevent concurrent over-allocation
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // Re-validate amount_due inside the lock
            if ($amount > (float) $invoice->amount_due) {
                throw new \InvalidArgumentException(
                    "Cannot allocate {$amount}. Invoice only has {$invoice->amount_due} due."
                );
            }

            $allocation = PaymentAllocation::create([
                'payment_received_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'base_amount' => bcmul((string) $amount, (string) $payment->exchange_rate, 4),
                'allocated_at' => now(),
            ]);

            // Update invoice atomically with the allocation
            $invoice->recordPayment($amount);

            return $allocation;
        });
    }

    /**
     * Deallocate payment from an invoice.
     */
    public function deallocate(PaymentAllocation $allocation): void
    {
        if ($allocation->payment->status !== PaymentReceived::STATUS_PENDING) {
            throw new \InvalidArgumentException('Can only modify pending payment allocations.');
        }

        DB::transaction(function () use ($allocation) {
            $invoice = $allocation->invoice;

            // Reverse payment on invoice
            $invoice->amount_paid = bcsub((string) $invoice->amount_paid, (string) $allocation->amount, 4);
            $invoice->amount_due = bcadd((string) $invoice->amount_due, (string) $allocation->amount, 4);

            if (bccomp((string) $invoice->amount_paid, '0', 4) <= 0) {
                $restoredStatus = ($invoice->due_date && $invoice->due_date->isPast())
                    ? Invoice::STATUS_OVERDUE
                    : Invoice::STATUS_SENT;
                $invoice->status = $restoredStatus;
            } elseif (bccomp((string) $invoice->amount_due, '0', 4) > 0) {
                $invoice->status = Invoice::STATUS_PARTIAL;
            }

            $invoice->save();

            $allocation->delete();
        });
    }

    /**
     * Apply customer credit to an invoice.
     */
    public function applyCredit(int $customerId, Invoice $invoice, ?float $amount = null): float
    {
        return DB::transaction(function () use ($customerId, $invoice, $amount): float {
            // Lock the invoice row to prevent concurrent credit applications
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $credits = CustomerCredit::forCustomer($customerId)
                ->active()
                ->where('currency_code', $invoice->currency_code)
                ->orderBy('credit_date')
                ->lockForUpdate()
                ->get();

            $totalApplied = '0';

            foreach ($credits as $credit) {
                if (bccomp((string) $invoice->amount_due, '0', 4) <= 0) {
                    break;
                }

                if ($amount !== null) {
                    $remaining = bcsub((string) $amount, $totalApplied, 4);
                    $toApply = min(
                        (float) $remaining,
                        (float) $credit->remaining_amount,
                        (float) $invoice->amount_due
                    );
                } else {
                    $toApply = min(
                        (float) $credit->remaining_amount,
                        (float) $invoice->amount_due
                    );
                }

                if ($toApply > 0) {
                    $applied = $credit->applyToInvoice($invoice, $toApply);
                    $totalApplied = bcadd($totalApplied, (string) $applied, 4);
                }

                if ($amount !== null && bccomp($totalApplied, (string) $amount, 4) >= 0) {
                    break;
                }
            }

            return (float) $totalApplied;
        });
    }

    /**
     * Create customer credit.
     */
    protected function createCustomerCredit(PaymentReceived $payment, float $amount): CustomerCredit
    {
        return CustomerCredit::create([
            'organization_id' => $payment->organization_id,
            'customer_id' => $payment->customer_id,
            'source_type' => CustomerCredit::SOURCE_OVERPAYMENT,
            'source_id' => $payment->id,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'currency_code' => $payment->currency_code,
            'credit_date' => $payment->payment_date,
            'notes' => "Overpayment from payment {$payment->payment_number}",
        ]);
    }

    /**
     * Create journal entry for payment.
     */
    protected function createJournalEntry(PaymentReceived $payment): \App\Models\Accounting\JournalEntry
    {
        return $this->journalEntryFactory->forPaymentReceived($payment);
    }

    /**
     * Clear open items: apply unallocated payments against the given invoices.
     * Returns a summary of cleared, partially cleared, and unmatched invoice IDs.
     */
    public function clearOpenItems(Contact $customer, array $invoiceIds, string $clearingDate): array
    {
        return DB::transaction(function () use ($customer, $invoiceIds, $clearingDate): array {
            // Load invoices ordered by date (oldest first)
            $invoices = Invoice::where('customer_id', $customer->id)
                ->whereIn('id', $invoiceIds)
                ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->orderBy('invoice_date')
                ->lockForUpdate()
                ->get();

            // Load unallocated payments for the customer
            $payments = PaymentReceived::where('customer_id', $customer->id)
                ->whereIn('status', [PaymentReceived::STATUS_COMPLETED, PaymentReceived::STATUS_PENDING])
                ->where(function ($q) {
                    $q->whereRaw('amount > COALESCE((SELECT SUM(amount) FROM payment_allocations WHERE payment_received_id = payment_received.id), 0)');
                })
                ->orderBy('payment_date')
                ->lockForUpdate()
                ->get();

            $cleared = [];
            $partial = [];
            $unmatched = [];

            foreach ($invoices as $invoice) {
                if ((float) $invoice->amount_due <= 0) {
                    $cleared[] = $invoice->id;
                    continue;
                }

                $allocated = false;

                foreach ($payments as $payment) {
                    $available = $payment->getUnallocatedAmount();

                    if ($available <= 0) {
                        continue;
                    }

                    $toAllocate = min($available, (float) $invoice->amount_due);

                    try {
                        $this->allocate($payment, $invoice, $toAllocate);
                        $allocated = true;
                        $invoice->refresh();
                    } catch (\InvalidArgumentException $e) {
                        \Illuminate\Support\Facades\Log::warning(
                            'Open-item clearing allocation skipped: ' . $e->getMessage()
                        );
                        continue;
                    }

                    if ((float) $invoice->amount_due <= 0) {
                        break;
                    }
                }

                if (!$allocated) {
                    $unmatched[] = $invoice->id;
                } elseif ((float) $invoice->amount_due > 0) {
                    $partial[] = $invoice->id;
                } else {
                    $cleared[] = $invoice->id;
                }
            }

            return [
                'clearing_date' => $clearingDate,
                'cleared' => $cleared,
                'partial' => $partial,
                'unmatched' => $unmatched,
            ];
        });
    }

    /**
     * Get customer statement.
     */
    public function getCustomerStatement(
        int $customerId,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $startDate = $startDate ?? now()->startOfYear();
        $endDate = $endDate ?? now();

        // Get opening balance
        $openingBalance = Invoice::forCustomer($customerId)
            ->where('invoice_date', '<', $startDate)
            ->sum('amount_due');

        // Get invoices in period
        $invoices = Invoice::forCustomer($customerId)
            ->inDateRange($startDate, $endDate)
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOIDED])
            ->orderBy('invoice_date')
            ->get();

        // Get payments in period
        $payments = PaymentReceived::forCustomer($customerId)
            ->inDateRange($startDate, $endDate)
            ->whereIn('status', [PaymentReceived::STATUS_COMPLETED])
            ->orderBy('payment_date')
            ->get();

        // Build statement lines
        $lines = [];
        $runningBalance = (float) $openingBalance;

        $allTransactions = collect()
            ->merge($invoices->map(fn($i) => ['type' => 'invoice', 'date' => $i->invoice_date, 'data' => $i]))
            ->merge($payments->map(fn($p) => ['type' => 'payment', 'date' => $p->payment_date, 'data' => $p]))
            ->sortBy('date');

        foreach ($allTransactions as $transaction) {
            if ($transaction['type'] === 'invoice') {
                $invoice = $transaction['data'];
                $runningBalance = bcadd((string) $runningBalance, (string) $invoice->total, 4);

                $lines[] = [
                    'date' => $invoice->invoice_date->toDateString(),
                    'type' => 'invoice',
                    'number' => $invoice->invoice_number,
                    'description' => "Invoice",
                    'debit' => $invoice->total,
                    'credit' => 0,
                    'balance' => $runningBalance,
                ];
            } else {
                $payment = $transaction['data'];
                $runningBalance = bcsub((string) $runningBalance, (string) $payment->amount, 4);

                $lines[] = [
                    'date' => $payment->payment_date->toDateString(),
                    'type' => 'payment',
                    'number' => $payment->payment_number,
                    'description' => "Payment - {$payment->getPaymentMethodLabel()}",
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'balance' => $runningBalance,
                ];
            }
        }

        return [
            'customer_id' => $customerId,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_invoiced' => $invoices->sum('total'),
            'total_paid' => $payments->sum('amount'),
            'lines' => $lines,
        ];
    }
}
