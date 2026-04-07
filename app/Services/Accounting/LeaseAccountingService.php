<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\LeaseContract;
use App\Models\Accounting\LeaseSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * IFRS 16 / ASC 842 Lease Accounting Service.
 *
 * Supports lessee finance and operating lease accounting:
 *  - Present value calculation using effective interest method
 *  - Amortisation schedule generation
 *  - Period-end journal posting (interest + principal, ROU depreciation)
 *  - Early termination and modification (lease liability remeasurement)
 */
class LeaseAccountingService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    // =========================================================================
    // List / fetch
    // =========================================================================

    public function index(int $organizationId, array $filters): LengthAwarePaginator
    {
        $query = LeaseContract::where('organization_id', $organizationId)
            ->with(['createdBy:id,name'])
            ->orderByDesc('commencement_date')
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // =========================================================================
    // Create
    // =========================================================================

    /**
     * Create a new lease contract and generate the full amortisation schedule.
     */
    public function create(array $data, int $organizationId, int $userId): LeaseContract
    {
        return DB::transaction(function () use ($data, $organizationId, $userId): LeaseContract {
            $data['organization_id'] = $organizationId;
            $data['created_by']      = $userId;
            $data['lease_number']    = $data['lease_number'] ?? $this->generateLeaseNumber($organizationId);

            // Calculate present value of lease liability
            $pv = $this->calculatePresentValue(
                payment: (float) $data['payment_amount'],
                periodicRate: $this->periodicRate((float) $data['discount_rate'], $data['payment_frequency'] ?? 'monthly'),
                periods: $this->totalPeriods((int) $data['lease_term_months'], $data['payment_frequency'] ?? 'monthly'),
            );

            $data['initial_rou_asset']           = $pv;
            $data['initial_lease_liability']     = $pv;
            $data['current_lease_liability']     = $pv;
            $data['status']                      = LeaseContract::STATUS_ACTIVE;

            $lease = LeaseContract::create($data);

            $this->buildSchedule($lease);

            return $lease->fresh(['schedule']);
        });
    }

    // =========================================================================
    // Amortisation schedule
    // =========================================================================

    /**
     * (Re-)build the full amortisation schedule for a lease.
     * Existing unposted lines are deleted and rebuilt.
     */
    public function buildSchedule(LeaseContract $lease): void
    {
        // Delete all unposted future lines
        $lease->schedule()->where('is_posted', false)->delete();

        $periodicRate  = $lease->periodicRate();
        $totalPeriods  = $lease->totalPeriods();
        $payment       = (float) $lease->payment_amount;
        $rouDepreciation = $lease->isFinanceLease()
            ? round((float) $lease->initial_rou_asset / $totalPeriods, 4)
            : 0.0;

        $balance      = (float) $lease->current_lease_liability;
        $paymentDate  = $lease->commencement_date->copy();
        $advance      = $this->advanceMonths($lease->payment_frequency);

        // Find the last posted period so we resume from there
        $lastPosted = $lease->schedule()->where('is_posted', true)->max('period_number') ?? 0;
        $startPeriod = $lastPosted + 1;

        $rows = [];
        for ($period = $startPeriod; $period <= $totalPeriods; $period++) {
            $paymentDate = $paymentDate->copy()->addMonths($advance);

            $interest   = round($balance * $periodicRate, 4);
            $principal  = round($payment - $interest, 4);
            $closing    = round($balance - $principal, 4);

            // Last period: absorb rounding difference
            if ($period === $totalPeriods) {
                $principal = $balance;
                $closing   = 0.0;
            }

            $rows[] = [
                'lease_contract_id' => $lease->id,
                'period_number'     => $period,
                'payment_date'      => $paymentDate->toDateString(),
                'opening_balance'   => $balance,
                'payment_amount'    => $payment,
                'interest_portion'  => $interest,
                'principal_portion' => $principal,
                'closing_balance'   => max(0.0, $closing),
                'rou_depreciation'  => $rouDepreciation,
                'is_posted'         => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $balance = max(0.0, $closing);
        }

        if (!empty($rows)) {
            LeaseSchedule::insert($rows);
        }
    }

    // =========================================================================
    // Period-end journal posting
    // =========================================================================

    /**
     * Post the journal entries for one lease period (SAP IFRS 16 periodic posting).
     *
     * Finance lease: debit Interest Expense + Lease Liability, credit Cash/Payable;
     *                debit Depreciation Expense, credit Accumulated Depreciation on ROU.
     * Operating lease: single straight-line expense entry.
     *
     * @throws InvalidArgumentException if the period is already posted or accounts not configured.
     */
    public function postPeriodEntry(LeaseContract $lease, int $periodNumber, array $journalMeta): LeaseSchedule
    {
        $line = $lease->schedule()->where('period_number', $periodNumber)->firstOrFail();

        if ($line->is_posted) {
            throw new InvalidArgumentException("Period {$periodNumber} has already been posted.");
        }

        return DB::transaction(function () use ($lease, $line, $journalMeta): LeaseSchedule {
            if ($lease->isFinanceLease()) {
                $je = $this->postFinanceLeaseEntry($lease, $line, $journalMeta);
            } else {
                $je = $this->postOperatingLeaseEntry($lease, $line, $journalMeta);
            }

            $newLiability = max(0.0, (float) $lease->current_lease_liability - (float) $line->principal_portion);

            $line->update([
                'is_posted'        => true,
                'journal_entry_id' => $je->id,
            ]);

            $lease->update(['current_lease_liability' => $newLiability]);

            return $line->fresh();
        });
    }

    // =========================================================================
    // Termination
    // =========================================================================

    /**
     * Terminate a lease early, derecognise ROU asset and remaining liability.
     */
    public function terminate(LeaseContract $lease, string $terminationDate, array $journalMeta): LeaseContract
    {
        if (!$lease->isActive()) {
            throw new InvalidArgumentException('Only active leases can be terminated.');
        }

        return DB::transaction(function () use ($lease, $terminationDate, $journalMeta): LeaseContract {
            // Derecognise remaining liability and ROU asset (simplified: book value = remaining)
            $remainingLiability = (float) $lease->current_lease_liability;

            if ($remainingLiability > 0 && $lease->leaseLiabilityAccount && $lease->rouAssetAccount) {
                $je = $this->journalService->createEntry(
                    array_merge($journalMeta, [
                        'description' => "Lease termination: {$lease->lease_number}",
                        'reference'   => 'LEASE-TERM-' . $lease->id,
                    ]),
                    [
                        [
                            'account_id'  => $lease->lease_liability_account_id,
                            'description' => 'Derecognise lease liability',
                            'debit'       => $remainingLiability,
                            'credit'      => 0,
                            'line_order'  => 1,
                        ],
                        [
                            'account_id'  => $lease->rou_asset_account_id,
                            'description' => 'Derecognise ROU asset',
                            'debit'       => 0,
                            'credit'      => $remainingLiability,
                            'line_order'  => 2,
                        ],
                    ]
                );
                $this->journalService->postEntry($je);
            }

            // Cancel remaining unposted schedule lines
            $lease->schedule()->where('is_posted', false)->delete();

            $lease->update([
                'status'               => LeaseContract::STATUS_TERMINATED,
                'termination_date'     => $terminationDate,
                'current_lease_liability' => 0,
            ]);

            return $lease->fresh();
        });
    }

    // =========================================================================
    // Modification / remeasurement
    // =========================================================================

    /**
     * Remeasure the lease liability after a modification (IFRS 16 para 45).
     * Recalculates PV with new terms and rebuilds the schedule.
     */
    public function modify(LeaseContract $lease, array $changes): LeaseContract
    {
        if (!$lease->isActive()) {
            throw new InvalidArgumentException('Only active leases can be modified.');
        }

        return DB::transaction(function () use ($lease, $changes): LeaseContract {
            $lease->fill($changes);

            $newPv = $this->calculatePresentValue(
                payment: (float) $lease->payment_amount,
                periodicRate: $lease->periodicRate(),
                periods: $lease->totalPeriods(),
            );

            $lease->current_lease_liability = $newPv;
            $lease->status                  = LeaseContract::STATUS_MODIFIED;
            $lease->save();

            // Rebuild remaining schedule
            $this->buildSchedule($lease);

            return $lease->fresh(['schedule']);
        });
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function postFinanceLeaseEntry(LeaseContract $lease, LeaseSchedule $line, array $meta): \App\Models\Accounting\JournalEntry
    {
        $this->requireAccounts($lease, ['lease_liability_account_id', 'interest_expense_account_id']);

        $lines = [];
        $order = 1;

        // Interest: Dr Interest Expense / Cr Lease Liability (accrual)
        if ((float) $line->interest_portion > 0) {
            $lines[] = [
                'account_id'  => $lease->interest_expense_account_id,
                'description' => "Lease interest period {$line->period_number}",
                'debit'       => (float) $line->interest_portion,
                'credit'      => 0,
                'line_order'  => $order++,
            ];
            $lines[] = [
                'account_id'  => $lease->lease_liability_account_id,
                'description' => "Lease liability interest accrual",
                'debit'       => 0,
                'credit'      => (float) $line->interest_portion,
                'line_order'  => $order++,
            ];
        }

        // Principal repayment: Dr Lease Liability / Cr (same liability, net-off handled by caller)
        // For simplicity, reduce liability via update (already done in postPeriodEntry).

        // ROU depreciation: Dr Depreciation Expense / Cr Accum Depreciation
        if ((float) $line->rou_depreciation > 0 && $lease->depreciation_expense_account_id && $lease->accum_depreciation_account_id) {
            $lines[] = [
                'account_id'  => $lease->depreciation_expense_account_id,
                'description' => "ROU asset depreciation period {$line->period_number}",
                'debit'       => (float) $line->rou_depreciation,
                'credit'      => 0,
                'line_order'  => $order++,
            ];
            $lines[] = [
                'account_id'  => $lease->accum_depreciation_account_id,
                'description' => "Accumulated depreciation on ROU asset",
                'debit'       => 0,
                'credit'      => (float) $line->rou_depreciation,
                'line_order'  => $order++,
            ];
        }

        if (empty($lines)) {
            // Nothing to post — create a zero-value memo entry
            $lines[] = [
                'account_id'  => $lease->lease_liability_account_id,
                'description' => "Lease period {$line->period_number} (zero interest)",
                'debit'       => 0,
                'credit'      => 0,
                'line_order'  => 1,
            ];
        }

        $je = $this->journalService->createEntry(
            array_merge($meta, [
                'description' => "IFRS16 finance lease: {$lease->lease_number} period {$line->period_number}",
                'reference'   => "LEASE-{$lease->id}-P{$line->period_number}",
            ]),
            $lines
        );
        $this->journalService->postEntry($je);

        return $je;
    }

    private function postOperatingLeaseEntry(LeaseContract $lease, LeaseSchedule $line, array $meta): \App\Models\Accounting\JournalEntry
    {
        // Operating lease: straight-line rent expense
        $this->requireAccounts($lease, ['interest_expense_account_id', 'lease_liability_account_id']);

        $expenseAmount = (float) $line->payment_amount;

        $je = $this->journalService->createEntry(
            array_merge($meta, [
                'description' => "Operating lease expense: {$lease->lease_number} period {$line->period_number}",
                'reference'   => "LEASE-{$lease->id}-P{$line->period_number}",
            ]),
            [
                [
                    'account_id'  => $lease->interest_expense_account_id,
                    'description' => "Rent expense period {$line->period_number}",
                    'debit'       => $expenseAmount,
                    'credit'      => 0,
                    'line_order'  => 1,
                ],
                [
                    'account_id'  => $lease->lease_liability_account_id,
                    'description' => "Lease payable period {$line->period_number}",
                    'debit'       => 0,
                    'credit'      => $expenseAmount,
                    'line_order'  => 2,
                ],
            ]
        );
        $this->journalService->postEntry($je);

        return $je;
    }

    private function requireAccounts(LeaseContract $lease, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($lease->$field)) {
                throw new InvalidArgumentException("GL account not configured for lease: {$field}");
            }
        }
    }

    /**
     * Present value of an annuity: PV = PMT × [(1 - (1+r)^(-n)) / r]
     */
    private function calculatePresentValue(float $payment, float $periodicRate, int $periods): float
    {
        if ($periodicRate <= 0 || $periods <= 0) {
            return round($payment * $periods, 4);
        }

        $pv = $payment * ((1 - pow(1 + $periodicRate, -$periods)) / $periodicRate);

        return round($pv, 4);
    }

    private function periodicRate(float $annualRate, string $frequency): float
    {
        return $annualRate / match ($frequency) {
            'monthly'     => 12,
            'quarterly'   => 4,
            'semi_annual' => 2,
            'annual'      => 1,
            default       => 12,
        };
    }

    private function totalPeriods(int $termMonths, string $frequency): int
    {
        return (int) round($termMonths / (12 / match ($frequency) {
            'monthly'     => 12,
            'quarterly'   => 4,
            'semi_annual' => 2,
            'annual'      => 1,
            default       => 12,
        }));
    }

    /** Number of months to advance per payment period. */
    private function advanceMonths(string $frequency): int
    {
        return match ($frequency) {
            'monthly'     => 1,
            'quarterly'   => 3,
            'semi_annual' => 6,
            'annual'      => 12,
            default       => 1,
        };
    }

    private function generateLeaseNumber(int $organizationId): string
    {
        $count = LeaseContract::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->count() + 1;

        return 'LEASE-' . now()->format('Y') . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
