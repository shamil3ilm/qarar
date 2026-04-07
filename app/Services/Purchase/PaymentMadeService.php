<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\Bill;
use App\Models\Purchase\BillPaymentAllocation;
use App\Models\Purchase\PaymentMade;
use App\Models\Purchase\SupplierCredit;
use App\Models\Tax\TdsConfiguration;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Tax\TdsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentMadeService
{
    public function __construct(
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private NumberGeneratorService $numberGenerator,
        private TdsService $tdsService
    ) {}

    /**
     * Create a new payment.
     */
    public function create(array $data, array $allocations = []): PaymentMade
    {
        return DB::transaction(function () use ($data, $allocations) {
            if (empty($data['payment_number'])) {
                $data['payment_number'] = $this->numberGenerator->generate('PAYM');
            }

            if (isset($data['exchange_rate']) && bccomp((string) $data['exchange_rate'], '0', 4) <= 0) {
                throw new \InvalidArgumentException('Exchange rate must be positive.');
            }

            $data['base_amount'] = bcmul(
                (string) $data['amount'],
                (string) ($data['exchange_rate'] ?? 1),
                4
            );

            // --- TDS auto-deduction (India-specific, SAP-style withholding tax) ---
            // TDS applies when: the organisation has TDS configured AND either
            //   (a) the caller explicitly supplies a tds_section_code in $data, or
            //   (b) the supplier record carries a tds_section_code (stored in $data by the controller).
            $tdsDeduction = null;
            $tdsSectionCode = $data['tds_section_code'] ?? null;
            $tdsDeducteeType = $data['tds_deductee_type'] ?? 'vendor';

            if ($tdsSectionCode !== null) {
                $orgId = $data['organization_id'] ?? auth()->user()?->organization_id;
                $tdsConfig = TdsConfiguration::where('organization_id', $orgId)
                    ->where('section_code', $tdsSectionCode)
                    ->first();

                if ($tdsConfig !== null) {
                    try {
                        $hasPan = !empty($data['supplier_pan']);
                        $tdsCalc = $this->tdsService->calculateTds(
                            deducteeType: $tdsDeducteeType,
                            paymentAmount: (float) $data['amount'],
                            sectionCode: $tdsSectionCode,
                            hasPan: $hasPan
                        );

                        if (!$tdsCalc['below_threshold'] && $tdsCalc['net_tds'] > 0) {
                            // Reduce the net payment by the TDS amount
                            $netPayable = bcsub(
                                (string) $data['amount'],
                                (string) $tdsCalc['net_tds'],
                                4
                            );
                            if (bccomp($netPayable, '0', 4) < 0) {
                                throw new \InvalidArgumentException('TDS deduction cannot exceed payment amount.');
                            }
                            $data['tds_amount'] = $tdsCalc['net_tds'];
                            $data['tds_section_id'] = $tdsCalc['section_id'] ?? null;
                            $data['net_payable_amount'] = (float) $netPayable;

                            // Will be recorded after the payment row is persisted (needs source_id).
                            $tdsDeduction = $tdsCalc;
                        }
                    } catch (\Throwable $e) {
                        // TDS section not found or misconfigured — log and continue without deduction.
                        Log::warning('TDS auto-deduction skipped', [
                            'section_code' => $tdsSectionCode,
                            'error'        => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Remove non-fillable TDS helper keys before persisting
            unset($data['tds_section_code'], $data['tds_deductee_type'], $data['supplier_pan']);

            $payment = PaymentMade::create($data);

            // Record TDS deduction entry now that we have the payment ID.
            if ($tdsDeduction !== null) {
                try {
                    $this->tdsService->recordDeduction([
                        'organization_id' => $payment->organization_id,
                        'deductee_type'   => $tdsDeducteeType,
                        'deductee_id'     => $payment->supplier_id,
                        'section_id'      => $tdsDeduction['section_id'],
                        'payment_date'    => $payment->payment_date->toDateString(),
                        'payment_amount'  => (float) $payment->amount,
                        'tds_rate'        => $tdsDeduction['tds_rate'],
                        'tds_amount'      => $tdsDeduction['tds_amount'],
                        'surcharge'       => $tdsDeduction['surcharge'],
                        'education_cess'  => $tdsDeduction['education_cess'],
                        'net_tds'         => $tdsDeduction['net_tds'],
                        'source_type'     => PaymentMade::class,
                        'source_id'       => $payment->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('TDS deduction record failed after payment creation', [
                        'payment_id' => $payment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $totalAllocated = 0;
            $orgId = $data['organization_id'] ?? ($payment->organization_id ?? null);

            if ($orgId === null) {
                throw new \RuntimeException('Organization ID is required for bill allocation.');
            }

            $billIds = array_column($allocations, 'bill_id');

            // Reject duplicate bill IDs in a single allocation batch
            if (count($billIds) !== count(array_unique($billIds))) {
                throw new \InvalidArgumentException('Duplicate bill IDs in allocations.');
            }

            $bills = Bill::whereIn('id', $billIds)
                ->where('organization_id', $orgId)
                ->get()
                ->keyBy('id');

            foreach ($allocations as $allocation) {
                $bill = $bills->get($allocation['bill_id'])
                    ?? throw new \InvalidArgumentException("Bill {$allocation['bill_id']} not found.");
                $amount = min($allocation['amount'], (float) $bill->amount_due);

                if ($amount > 0) {
                    // During initial creation, skip currency validation to allow flexible allocation
                    BillPaymentAllocation::create([
                        'payment_made_id' => $payment->id,
                        'bill_id' => $bill->id,
                        'amount' => $amount,
                        'base_amount' => bcmul((string) $amount, (string) $payment->exchange_rate, 4),
                        'allocated_at' => now(),
                    ]);
                    $bill->recordPayment($amount);
                    $totalAllocated = bcadd((string) $totalAllocated, (string) $amount, 4);
                }
            }

            $unallocated = bcsub((string) $payment->amount, (string) $totalAllocated, 4);
            if (bccomp($unallocated, '0', 4) > 0) {
                $this->createSupplierCredit($payment, (float) $unallocated);
            }

            return $payment->load('allocations.bill', 'supplier');
        });
    }

    /**
     * Complete/confirm a payment.
     */
    public function complete(PaymentMade $payment, int $userId): PaymentMade
    {
        if ($payment->status !== PaymentMade::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending payments can be completed.');
        }

        return DB::transaction(function () use ($payment, $userId) {
            $journalId = null;

            try {
                $journal = $this->createJournalEntry($payment);
                $journalId = $journal->id;
            } catch (\Exception $e) {
                // Do not re-throw — payment completion should not fail due to missing
                // GL configuration — but log so operators know the journal is absent.
                Log::error('PaymentMade journal entry creation failed', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $payment->update([
                'status' => PaymentMade::STATUS_COMPLETED,
                'journal_entry_id' => $journalId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Void a payment.
     */
    public function void(PaymentMade $payment, string $reason = ''): PaymentMade
    {
        if ($payment->status === PaymentMade::STATUS_VOIDED) {
            throw new \InvalidArgumentException('Payment is already voided.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            foreach ($payment->allocations as $allocation) {
                $bill = $allocation->bill;
                $bill->amount_paid = bcsub((string) $bill->amount_paid, (string) $allocation->amount, 4);
                $bill->amount_due = bcadd((string) $bill->amount_due, (string) $allocation->amount, 4);

                if (bccomp((string) $bill->amount_paid, '0', 4) <= 0) {
                    $bill->status = Bill::STATUS_APPROVED;
                } else {
                    $bill->status = Bill::STATUS_PARTIAL;
                }

                $bill->save();
            }

            $payment->allocations()->delete();

            SupplierCredit::where('source_type', SupplierCredit::SOURCE_OVERPAYMENT)
                ->where('source_id', $payment->id)
                ->update(['is_active' => false, 'remaining_amount' => 0]);

            if ($payment->journal_entry_id && ($journalEntry = $payment->journalEntry)) {
                $this->journalService->void($journalEntry, $reason);
            }

            $payment->update([
                'status' => PaymentMade::STATUS_VOIDED,
                'notes' => $payment->notes . "\n\nVoided: " . $reason,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Allocate payment to a bill.
     */
    public function allocate(PaymentMade $payment, Bill $bill, float $amount): BillPaymentAllocation
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive.');
        }

        return DB::transaction(function () use ($payment, $bill, $amount) {
            // Lock the payment row first to prevent concurrent over-allocation
            $payment = PaymentMade::lockForUpdate()->findOrFail($payment->id);

            // Lock the bill row to prevent concurrent double-allocation
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            $available = $payment->getUnallocatedAmount();
            if ($amount > $available) {
                throw new \InvalidArgumentException("Cannot allocate {$amount}. Only {$available} available.");
            }

            if ($amount > $bill->amount_due) {
                throw new \InvalidArgumentException("Cannot allocate {$amount}. Bill only has {$bill->amount_due} due.");
            }

            // Currency validation is handled at the controller level if needed

            $allocation = BillPaymentAllocation::create([
                'payment_made_id' => $payment->id,
                'bill_id' => $bill->id,
                'amount' => $amount,
                'base_amount' => bcmul((string) $amount, (string) $payment->exchange_rate, 4),
                'allocated_at' => now(),
            ]);

            $bill->recordPayment($amount);

            return $allocation;
        });
    }

    /**
     * Create supplier credit.
     */
    protected function createSupplierCredit(PaymentMade $payment, float $amount): SupplierCredit
    {
        return SupplierCredit::create([
            'organization_id' => $payment->organization_id,
            'supplier_id' => $payment->supplier_id,
            'source_type' => SupplierCredit::SOURCE_OVERPAYMENT,
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
    protected function createJournalEntry(PaymentMade $payment): \App\Models\Accounting\JournalEntry
    {
        return $this->journalEntryFactory->forPaymentMade($payment);
    }

    /**
     * Get supplier statement.
     */
    public function getSupplierStatement(
        int $supplierId,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $startDate = $startDate ?? now()->startOfYear();
        $endDate = $endDate ?? now();

        $openingBalance = Bill::forSupplier($supplierId)
            ->where('bill_date', '<', $startDate)
            ->sum('amount_due');

        $bills = Bill::forSupplier($supplierId)
            ->inDateRange($startDate, $endDate)
            ->whereNotIn('status', [Bill::STATUS_DRAFT, Bill::STATUS_VOIDED])
            ->orderBy('bill_date')
            ->get();

        $payments = PaymentMade::forSupplier($supplierId)
            ->inDateRange($startDate, $endDate)
            ->completed()
            ->orderBy('payment_date')
            ->get();

        $lines = [];
        $runningBalance = (float) $openingBalance;

        $allTransactions = collect()
            ->merge($bills->map(fn($b) => ['type' => 'bill', 'date' => $b->bill_date, 'data' => $b]))
            ->merge($payments->map(fn($p) => ['type' => 'payment', 'date' => $p->payment_date, 'data' => $p]))
            ->sortBy('date');

        foreach ($allTransactions as $transaction) {
            if ($transaction['type'] === 'bill') {
                $bill = $transaction['data'];
                $runningBalance = bcadd((string) $runningBalance, (string) $bill->total, 4);

                $lines[] = [
                    'date' => $bill->bill_date->toDateString(),
                    'type' => 'bill',
                    'number' => $bill->bill_number,
                    'description' => "Bill",
                    'debit' => 0,
                    'credit' => $bill->total,
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
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'balance' => $runningBalance,
                ];
            }
        }

        return [
            'supplier_id' => $supplierId,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_billed' => $bills->sum('total'),
            'total_paid' => $payments->sum('amount'),
            'lines' => $lines,
        ];
    }
}
