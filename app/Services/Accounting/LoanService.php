<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanPayment;
use App\Models\Accounting\LoanSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LoanService
{
    /**
     * Create a new loan.
     */
    public function create(array $data, int $userId): Loan
    {
        return DB::transaction(function () use ($data, $userId) {
            // Calculate total interest and total amount
            $principal = (float) $data['principal_amount'];
            $rate = (float) ($data['interest_rate'] ?? 0);
            $tenureMonths = (int) $data['tenure_months'];
            $interestType = $data['interest_type'] ?? Loan::INTEREST_TYPE_SIMPLE;

            $totalInterest = $this->calculateTotalInterest($principal, $rate, $tenureMonths, $interestType);
            $totalAmount = (float) bcadd((string) $principal, (string) $totalInterest, 4);
            $emiAmount = $this->calculateEmi($principal, $rate, $tenureMonths, $interestType);

            $loan = Loan::create([
                'organization_id' => $data['organization_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'loan_type' => $data['loan_type'],
                'loan_category' => $data['loan_category'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'borrower_name' => $data['borrower_name'] ?? null,
                'lender_type' => $data['lender_type'] ?? Loan::LENDER_ORGANIZATION,
                'lender_name' => $data['lender_name'] ?? null,
                'lender_contact_id' => $data['lender_contact_id'] ?? null,
                'principal_amount' => $principal,
                'interest_rate' => $rate,
                'interest_type' => $interestType,
                'total_interest' => $totalInterest,
                'total_amount' => $totalAmount,
                'outstanding_amount' => $totalAmount,
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'disbursement_date' => $data['disbursement_date'],
                'first_payment_date' => $data['first_payment_date'],
                'maturity_date' => $data['maturity_date'],
                'tenure_months' => $tenureMonths,
                'payment_frequency' => $data['payment_frequency'] ?? Loan::FREQUENCY_MONTHLY,
                'emi_amount' => $emiAmount,
                'total_installments' => $data['total_installments'] ?? $tenureMonths,
                'loan_account_id' => $data['loan_account_id'] ?? null,
                'interest_account_id' => $data['interest_account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'deduct_from_payroll' => $data['deduct_from_payroll'] ?? false,
                'monthly_deduction' => $data['monthly_deduction'] ?? $emiAmount,
                'purpose' => $data['purpose'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'documents' => $data['documents'] ?? null,
                'created_by' => $data['created_by'] ?? $userId,
            ]);

            // Auto-generate schedule
            $this->generateSchedule($loan);

            return $loan->fresh(['schedules', 'createdBy']);
        });
    }

    /**
     * Generate repayment schedule for a loan.
     */
    public function generateSchedule(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            // Delete existing schedule
            $loan->schedules()->delete();

            $principal = (float) $loan->principal_amount;
            $rate = (float) $loan->interest_rate;
            $totalInstallments = $loan->total_installments;
            $interestType = $loan->interest_type;
            $emi = (float) $loan->emi_amount;

            $outstandingBalance = (float) $loan->total_amount;
            $paymentDate = $loan->first_payment_date->copy();

            $monthlyRate = (float) bcdiv(bcdiv((string)$rate, '12', 8), '100', 8);
            $remainingPrincipal = $principal;

            for ($i = 1; $i <= $totalInstallments; $i++) {
                if ($interestType === Loan::INTEREST_TYPE_FLAT) {
                    $interestAmount = (float) bcdiv((string) $loan->total_interest, (string) $totalInstallments, 4);
                    $principalAmount = (float) bcdiv((string) $principal, (string) $totalInstallments, 4);
                } elseif ($interestType === Loan::INTEREST_TYPE_SIMPLE) {
                    $interestAmount = (float) bcmul((string) $remainingPrincipal, (string) $monthlyRate, 4);
                    $principalAmount = (float) bcsub((string) $emi, (string) $interestAmount, 4);
                } else {
                    // Compound interest
                    $interestAmount = (float) bcmul((string) $remainingPrincipal, (string) $monthlyRate, 4);
                    $principalAmount = (float) bcsub((string) $emi, (string) $interestAmount, 4);
                }

                // Last installment adjustment
                if ($i === $totalInstallments) {
                    $principalAmount = $remainingPrincipal;
                    $totalAmount = (float) bcadd((string) $principalAmount, (string) $interestAmount, 4);
                } else {
                    $totalAmount = (float) bcadd((string) $principalAmount, (string) $interestAmount, 4);
                }

                $remainingPrincipal = (float) bcsub((string) $remainingPrincipal, (string) $principalAmount, 4);
                $outstandingBalance = (float) bcsub((string) $outstandingBalance, (string) $totalAmount, 4);

                LoanSchedule::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $i,
                    'due_date' => $paymentDate->toDateString(),
                    'principal_amount' => round($principalAmount, 2),
                    'interest_amount' => round($interestAmount, 2),
                    'total_amount' => round($totalAmount, 2),
                    'outstanding_balance' => max(0, round($outstandingBalance, 2)),
                ]);

                // Advance to next payment date based on frequency
                $paymentDate = match ($loan->payment_frequency) {
                    Loan::FREQUENCY_WEEKLY => $paymentDate->addWeek(),
                    Loan::FREQUENCY_BI_WEEKLY => $paymentDate->addWeeks(2),
                    default => $paymentDate->addMonth(),
                };
            }

            // Reconcile rounding errors on the last installment so that
            // sum(principal) == principal_amount and sum(interest) == total_interest.
            $lastLine = LoanSchedule::where('loan_id', $loan->id)
                ->orderByDesc('installment_number')
                ->first();

            if ($lastLine !== null) {
                $scheduleLines = LoanSchedule::where('loan_id', $loan->id)->get();
                $totalPrincipal = $scheduleLines->sum('principal_amount');
                $totalInterest = $scheduleLines->sum('interest_amount');
                $principalDiff = bcsub((string) $loan->principal_amount, (string) $totalPrincipal, 4);
                $interestDiff = bcsub((string) $loan->total_interest, (string) $totalInterest, 4);
                $lastLine->principal_amount = bcadd((string) $lastLine->principal_amount, (string) $principalDiff, 4);
                $lastLine->interest_amount = bcadd((string) $lastLine->interest_amount, (string) $interestDiff, 4);
                $lastLine->total_amount = bcadd((string) $lastLine->principal_amount, (string) $lastLine->interest_amount, 4);
                $lastLine->save();
            }

            return $loan->fresh('schedules');
        });
    }

    /**
     * Record a loan payment.
     */
    public function recordPayment(Loan $loan, array $data, int $userId): LoanPayment
    {
        if (!in_array($loan->status, [Loan::STATUS_ACTIVE, Loan::STATUS_APPROVED])) {
            throw new InvalidArgumentException('Payments can only be recorded for active or approved loans.');
        }

        // Fix 9: Assert the period is not locked for the payment date before recording.
        $paymentDateStr = is_string($data['payment_date'])
            ? $data['payment_date']
            : $data['payment_date']->toDateString();
        app(\App\Services\Accounting\PeriodLockService::class)
            ->assertNotLocked($loan->organization_id, $paymentDateStr, $userId);

        $totalPaid = bcadd((string) ($data['principal_paid'] ?? 0), (string) ($data['interest_paid'] ?? 0), 4);
        if (bccomp($totalPaid, '0', 4) <= 0) {
            throw new \App\Exceptions\ApiException('Payment must include a positive principal or interest amount.');
        }

        return DB::transaction(function () use ($loan, $data, $userId) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);

            $principalPaid = (float) ($data['principal_paid'] ?? 0);
            $interestPaid = (float) ($data['interest_paid'] ?? 0);
            $penaltyPaid = (float) ($data['penalty_paid'] ?? 0);

            if ($principalPaid < 0 || $interestPaid < 0 || $penaltyPaid < 0) {
                throw new \InvalidArgumentException('Payment amounts cannot be negative.');
            }

            $totalPaid = (float) bcadd(bcadd((string) $principalPaid, (string) $interestPaid, 4), (string) $penaltyPaid, 4);

            $payment = LoanPayment::create([
                'loan_id' => $loan->id,
                'schedule_id' => $data['schedule_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'principal_paid' => $principalPaid,
                'interest_paid' => $interestPaid,
                'penalty_paid' => $penaltyPaid,
                'total_paid' => $totalPaid,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'journal_entry_id' => $data['journal_entry_id'] ?? null,
                'payroll_id' => $data['payroll_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $data['received_by'] ?? $userId,
            ]);

            // Update schedule if linked
            if ($payment->schedule_id) {
                $schedule = LoanSchedule::find($payment->schedule_id);
                if ($schedule) {
                    $newPaidAmount = (float) bcadd((string) $schedule->paid_amount, (string) $totalPaid, 4);
                    $schedule->update([
                        'paid_amount' => $newPaidAmount,
                        'paid_date' => $data['payment_date'],
                        'status' => $newPaidAmount >= $schedule->total_amount
                            ? LoanSchedule::STATUS_PAID
                            : LoanSchedule::STATUS_PARTIAL,
                    ]);
                }
            }

            // Update loan outstanding balance and paid installments
            $loan->outstanding_amount = bcsub(
                (string) $loan->outstanding_amount,
                (string) $totalPaid,
                4
            );
            $loan->paid_installments = $loan->schedules()
                ->where('status', LoanSchedule::STATUS_PAID)
                ->count();

            // Auto-activate if pending/approved and first payment
            if ($loan->status === Loan::STATUS_APPROVED || $loan->status === Loan::STATUS_PENDING) {
                $loan->status = Loan::STATUS_ACTIVE;
            }

            // Auto-complete if fully paid
            if ((float) $loan->outstanding_amount <= 0) {
                $loan->outstanding_amount = 0;
                $loan->status = Loan::STATUS_COMPLETED;
            }

            $loan->save();

            // Create GL journal entry for the loan payment.
            try {
                $orgId = $loan->organization_id;

                // Resolve loan liability account.
                $loanAccount = null;
                if (!empty($loan->loan_account_id)) {
                    $loanAccount = Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->where('id', $loan->loan_account_id)
                        ->first();
                }
                if ($loanAccount === null) {
                    $loanAccount = Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->whereIn('account_type', ['liability'])
                        ->where(function ($q) {
                            $q->where('name', 'like', '%loan%')
                              ->orWhere('name', 'like', '%Loan%');
                        })
                        ->first();
                }

                // Resolve interest expense account.
                $interestAccount = null;
                if ($interestPaid > 0 && !empty($loan->interest_account_id)) {
                    $interestAccount = Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->where('id', $loan->interest_account_id)
                        ->first();
                }

                // Resolve bank/cash credit account from payment bank account.
                $bankGlAccount = null;
                if (!empty($loan->bank_account_id)) {
                    $bankRecord = BankAccount::find($loan->bank_account_id);
                    if ($bankRecord && !empty($bankRecord->gl_account_id)) {
                        $bankGlAccount = Account::withoutGlobalScopes()
                            ->where('organization_id', $orgId)
                            ->where('id', $bankRecord->gl_account_id)
                            ->first();
                    }
                }
                if ($bankGlAccount === null) {
                    $bankGlAccount = Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->whereIn('account_type', ['bank', 'cash'])
                        ->first();
                }

                if ($loanAccount === null || $bankGlAccount === null) {
                    Log::warning('LoanService: Missing GL accounts for loan payment journal entry.', [
                        'loan_id'        => $loan->id,
                        'payment_id'     => $payment->id,
                        'has_loan_acct'  => $loanAccount !== null,
                        'has_bank_acct'  => $bankGlAccount !== null,
                    ]);
                } else {
                    $journalLines = [];

                    // Debit loan liability for principal portion.
                    if ($principalPaid > 0) {
                        $journalLines[] = [
                            'account_id'  => $loanAccount->id,
                            'description' => 'Loan principal payment',
                            'debit'       => $principalPaid,
                            'credit'      => 0,
                            'line_order'  => 0,
                        ];
                    }

                    // Debit interest expense for interest portion.
                    if ($interestPaid > 0) {
                        $interestDebitAccountId = $interestAccount?->id ?? $loanAccount->id;
                        $journalLines[] = [
                            'account_id'  => $interestDebitAccountId,
                            'description' => 'Loan interest payment',
                            'debit'       => $interestPaid,
                            'credit'      => 0,
                            'line_order'  => count($journalLines),
                        ];
                    }

                    // Credit bank/cash for total payment.
                    $journalLines[] = [
                        'account_id'  => $bankGlAccount->id,
                        'description' => 'Loan payment disbursed',
                        'debit'       => 0,
                        'credit'      => $totalPaid,
                        'line_order'  => count($journalLines),
                    ];

                    if (!empty($journalLines)) {
                        app(JournalService::class)->createEntry(
                            [
                                'organization_id' => $orgId,
                                'entry_date'      => is_string($data['payment_date'])
                                    ? $data['payment_date']
                                    : $data['payment_date']->toDateString(),
                                'reference'       => 'LOAN-PMT-' . $payment->id,
                                'description'     => "Loan payment for loan #{$loan->id}",
                                'source_type'     => LoanPayment::class,
                                'source_id'       => $payment->id,
                            ],
                            $journalLines
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('LoanService: Failed to create journal entry for loan payment.', [
                    'loan_id'    => $loan->id,
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return $payment->fresh(['loan', 'schedule', 'receivedBy']);
        });
    }

    /**
     * Get the outstanding balance for a loan.
     */
    public function getOutstandingBalance(Loan $loan): array
    {
        $totalPaid = $loan->payments()->sum('total_paid');
        $totalPrincipalPaid = $loan->payments()->sum('principal_paid');
        $totalInterestPaid = $loan->payments()->sum('interest_paid');

        $nextSchedule = $loan->schedules()
            ->whereIn('status', [LoanSchedule::STATUS_PENDING, LoanSchedule::STATUS_PARTIAL, LoanSchedule::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->first();

        $overdueSchedules = $loan->schedules()
            ->where('status', LoanSchedule::STATUS_OVERDUE)
            ->get();

        return [
            'loan_id' => $loan->id,
            'principal_amount' => (float) $loan->principal_amount,
            'total_amount' => (float) $loan->total_amount,
            'outstanding_amount' => (float) $loan->outstanding_amount,
            'total_paid' => (float) $totalPaid,
            'total_principal_paid' => (float) $totalPrincipalPaid,
            'total_interest_paid' => (float) $totalInterestPaid,
            'paid_installments' => $loan->paid_installments,
            'total_installments' => $loan->total_installments,
            'next_due_date' => $nextSchedule?->due_date?->toDateString(),
            'next_due_amount' => $nextSchedule ? (float) $nextSchedule->getRemainingAmount() : 0,
            'overdue_count' => $overdueSchedules->count(),
            'overdue_amount' => (float) $overdueSchedules->sum(fn ($s) => $s->getRemainingAmount()),
        ];
    }

    /**
     * Close a completed loan.
     */
    public function close(Loan $loan): Loan
    {
        if ($loan->status === Loan::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Loan is already completed.');
        }

        if ($loan->outstanding_amount > 0) {
            throw new InvalidArgumentException(
                "Cannot close loan with outstanding balance of {$loan->outstanding_amount}."
            );
        }

        return DB::transaction(function () use ($loan) {
            $loan->update(['status' => Loan::STATUS_COMPLETED]);
            return $loan->fresh();
        });
    }

    /**
     * Calculate total interest for a loan.
     */
    private function calculateTotalInterest(float $principal, float $rate, int $months, string $type): float
    {
        if ($rate <= 0) {
            return 0;
        }

        $monthlyRate = (float) bcdiv(bcdiv((string)$rate, '12', 8), '100', 8);

        return match ($type) {
            Loan::INTEREST_TYPE_FLAT => (float) bcmul(
                (string) $principal,
                (string) bcmul(bcdiv((string) $rate, '100', 8), bcdiv((string) $months, '12', 8), 8),
                4
            ),
            Loan::INTEREST_TYPE_SIMPLE => $this->calculateSimpleInterestTotal($principal, $monthlyRate, $months),
            Loan::INTEREST_TYPE_COMPOUND => $this->calculateCompoundInterestTotal($principal, $monthlyRate, $months),
            default => throw new \InvalidArgumentException("Unknown interest type: {$type}"),
        };
    }

    private function calculateSimpleInterestTotal(float $principal, float $monthlyRate, int $months): float
    {
        if ($monthlyRate <= 0) {
            return 0;
        }

        // pow() is used here because bcmath has no fractional-exponent function;
        // the result is converted back to bcmath for the final monetary subtraction.
        $compoundFactor = pow(1 + $monthlyRate, $months);
        $denominator    = bcsub((string) $compoundFactor, '1', 8);
        if (bccomp($denominator, '0', 8) === 0) {
            return (float) bcdiv((string) $principal, (string) $months, 4);
        }
        $numerator = bcmul(bcmul((string) $principal, (string) $monthlyRate, 8), (string) $compoundFactor, 8);
        $emi       = bcdiv($numerator, $denominator, 4);
        $totalPaid = bcmul($emi, (string) $months, 4);

        return (float) bcsub($totalPaid, (string) $principal, 4);
    }

    private function calculateCompoundInterestTotal(float $principal, float $monthlyRate, int $months): float
    {
        // pow() is required for the fractional compound-interest exponent.
        $compoundFactor = pow(1 + $monthlyRate, $months);

        return (float) bcmul((string) $principal, (string) ($compoundFactor - 1), 4);
    }

    /**
     * Calculate EMI (Equated Monthly Installment).
     */
    private function calculateEmi(float $principal, float $rate, int $months, string $type): float
    {
        if ($months <= 0) {
            return $principal;
        }

        if ($rate <= 0) {
            return (float) bcdiv((string) $principal, (string) $months, 4);
        }

        $monthlyRate = bcdiv(bcdiv((string)$rate, '12', 8), '100', 8);

        if ($type === Loan::INTEREST_TYPE_FLAT) {
            $totalInterest = bcmul(
                (string) $principal,
                (string) bcmul(bcdiv((string) $rate, '100', 8), bcdiv((string) $months, '12', 8), 8),
                4
            );
            return (float) bcdiv(bcadd((string) $principal, $totalInterest, 4), (string) $months, 4);
        }

        // Standard EMI formula for reducing balance using bcmath
        $factor = '1';
        for ($i = 0; $i < $months; $i++) {
            $factor = bcmul($factor, bcadd('1', $monthlyRate, 8), 8);
        }
        $emi = bcdiv(bcmul((string)$principal, bcmul($monthlyRate, $factor, 8), 8), bcsub($factor, '1', 8), 4);

        return round((float)$emi, 2);
    }
}
