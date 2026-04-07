<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Accounting\Account;
use App\Models\HR\Employee;
use App\Models\HR\EosbPolicy;
use App\Models\HR\EosbProvision;
use App\Models\HR\EosbSettlement;
use App\Services\Accounting\JournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EosbService
{
    /**
     * Calculate and record the monthly EOSB provision for an employee.
     */
    public function calculateMonthlyProvision(Employee $employee, ?int $year = null, ?int $month = null): EosbProvision
    {
        if ($employee->joining_date === null) {
            throw new \RuntimeException('Cannot calculate EOSB: employee joining date is not set.');
        }

        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        $policy = $this->resolvePolicy($employee);

        $salary = $employee->currentSalary;
        if (!$salary) {
            throw new \InvalidArgumentException("Employee {$employee->id} has no active salary.");
        }

        if (bccomp((string)($salary->basic_salary ?? 0), '0', 4) <= 0) {
            throw new \InvalidArgumentException('Employee basic salary must be positive for EOSB calculation.');
        }

        $basicSalary = (float) $salary->basic_salary;
        $dailyRate = bcdiv((string) $basicSalary, '30', 4);

        $tenureMonths = $employee->getTenureInMonths() ?? 0;

        if ($tenureMonths < $policy->min_service_months) {
            $daysEarned = 0.0;
        } else {
            $daysEarned = $this->computeMonthlyDaysEarned($policy, $tenureMonths);
        }

        $provisionAmount = bcmul((string)$dailyRate, (string)$daysEarned, 4);

        $prevMonth = $month - 1 > 0 ? $month - 1 : 12;
        $prevYear  = $month - 1 > 0 ? $year : $year - 1;

        return DB::transaction(function () use (
            $employee,
            $year,
            $month,
            $prevYear,
            $prevMonth,
            $policy,
            $daysEarned,
            $dailyRate,
            $provisionAmount
        ) {
            $previous = EosbProvision::forEmployee($employee->id)
                ->where('period_year', $prevYear)
                ->where('period_month', $prevMonth)
                ->lockForUpdate()
                ->first();

            $cumulativeAmount = bcadd((string) ($previous?->cumulative_amount ?? '0'), (string) $provisionAmount, 4);

            return EosbProvision::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'period_year' => $year,
                    'period_month' => $month,
                ],
                [
                    'organization_id' => $employee->organization_id,
                    'eosb_policy_id' => $policy->id,
                    'days_earned' => $daysEarned,
                    'daily_rate' => $dailyRate,
                    'provision_amount' => $provisionAmount,
                    'cumulative_amount' => $cumulativeAmount,
                    'basic_salary_used' => (float) ($employee->currentSalary->basic_salary ?? 0),
                ]
            );
        });
    }

    /**
     * Calculate the final settlement amount for an employee.
     */
    public function calculateFinalSettlement(Employee $employee, Carbon $terminationDate, ?string $terminationReason = null): array
    {
        $policy = $this->resolvePolicy($employee);

        $joiningDate = $employee->joining_date;
        if (!$joiningDate) {
            throw new \InvalidArgumentException('Employee joining date is required for settlement calculation.');
        }

        $yearsOfService = round($joiningDate->diffInDays($terminationDate) / 365, 4);
        $totalMonths = $joiningDate->diffInMonths($terminationDate);

        if ($totalMonths < $policy->min_service_months) {
            return [
                'years_of_service' => $yearsOfService,
                'total_days_earned' => 0,
                'daily_rate' => 0,
                'gross_amount' => 0,
                'message' => "Minimum service period ({$policy->min_service_months} months) not met.",
            ];
        }

        $salary = $employee->currentSalary;
        $basicSalary = $salary ? (float) $salary->basic_salary : 0.0;
        $dailyRate = bcdiv((string) $basicSalary, '30', 4);

        $totalDaysEarned = $this->computeTotalDaysEarned($policy, $yearsOfService);

        // KSA Labor Law Article 84: voluntary resignation reduces entitlement
        if (($terminationReason ?? '') === 'resignation') {
            if ($yearsOfService < 2) {
                $totalDaysEarned = 0.0;
            } elseif ($yearsOfService < 5) {
                $totalDaysEarned = bcdiv((string) $totalDaysEarned, '3', 4);
            } elseif ($yearsOfService < 10) {
                $totalDaysEarned = bcdiv(bcmul((string) $totalDaysEarned, '2', 4), '3', 4);
            }
            // 10+ years: full entitlement, no change
        }

        $grossAmount = bcmul((string) $dailyRate, (string) $totalDaysEarned, 4);

        return [
            'years_of_service' => $yearsOfService,
            'total_days_earned' => $totalDaysEarned,
            'daily_rate' => $dailyRate,
            'gross_amount' => $grossAmount,
            'policy_id' => $policy->id,
            'basic_salary_used' => $basicSalary,
        ];
    }

    /**
     * Generate and persist an EOSB settlement record.
     */
    public function generateSettlement(Employee $employee, array $data): EosbSettlement
    {
        $terminationDate = Carbon::parse($data['termination_date']);

        $calculated = $this->calculateFinalSettlement($employee, $terminationDate, $data['termination_reason'] ?? null);

        return DB::transaction(function () use ($employee, $data, $calculated, $terminationDate) {
            return EosbSettlement::create([
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'eosb_policy_id' => $calculated['policy_id'] ?? $this->resolvePolicy($employee)->id,
                'termination_date' => $terminationDate,
                'years_of_service' => $calculated['years_of_service'],
                'total_days_earned' => $calculated['total_days_earned'],
                'daily_rate' => $calculated['daily_rate'],
                'gross_amount' => $calculated['gross_amount'],
                'deductions' => $data['deductions'] ?? 0,
                'net_amount' => bcsub((string) $calculated['gross_amount'], (string) ($data['deductions'] ?? 0), 4),
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'payment_date' => $data['payment_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => EosbSettlement::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Approve an EOSB settlement.
     */
    public function approveSettlement(EosbSettlement $settlement): EosbSettlement
    {
        if (!$settlement->canBeApproved()) {
            throw new \InvalidArgumentException('Settlement cannot be approved in its current state.');
        }

        $settlement->update([
            'status' => EosbSettlement::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $settlement->fresh();
    }

    /**
     * Mark a settlement as paid.
     */
    public function markSettlementPaid(EosbSettlement $settlement, string $paymentDate): EosbSettlement
    {
        if (!$settlement->canBePaid()) {
            throw new \InvalidArgumentException('Only approved settlements can be marked as paid.');
        }

        return DB::transaction(function () use ($settlement, $paymentDate) {
            $settlement->update([
                'status' => EosbSettlement::STATUS_PAID,
                'payment_date' => $paymentDate,
            ]);

            try {
                $orgId = $settlement->organization_id;

                $liabilityAccount = Account::where('organization_id', $orgId)
                    ->where('code', '2200')
                    ->first()
                    ?? Account::where('organization_id', $orgId)
                        ->whereIn('account_type', ['liability'])
                        ->where(function ($q) {
                            $q->where('name', 'like', '%eosb%')
                              ->orWhere('name', 'like', '%EOSB%')
                              ->orWhere('name', 'like', '%end of service%');
                        })
                        ->first()
                    ?? Account::where('organization_id', $orgId)
                        ->whereIn('account_type', ['liability'])
                        ->first();

                $bankAccount = Account::where('organization_id', $orgId)
                    ->whereIn('account_type', ['bank', 'cash'])
                    ->first();

                if ($liabilityAccount === null) {
                    Log::warning('EosbService: No liability account found for EOSB journal entry.', [
                        'organization_id' => $orgId,
                        'settlement_id'   => $settlement->id,
                    ]);
                } elseif ($bankAccount === null) {
                    Log::warning('EosbService: No bank/cash account found for EOSB journal entry.', [
                        'organization_id' => $orgId,
                        'settlement_id'   => $settlement->id,
                    ]);
                } else {
                    $amount = (float) $settlement->net_amount;
                    app(JournalService::class)->createEntry(
                        [
                            'organization_id' => $orgId,
                            'entry_date'      => $paymentDate,
                            'reference'       => 'EOSB-SETTLE-' . $settlement->id,
                            'description'     => 'EOSB settlement payment for employee ' . $settlement->employee_id,
                            'source_type'     => EosbSettlement::class,
                            'source_id'       => $settlement->id,
                        ],
                        [
                            [
                                'account_id'  => $liabilityAccount->id,
                                'description' => 'EOSB liability cleared',
                                'debit'       => $amount,
                                'credit'      => 0,
                                'line_order'  => 0,
                            ],
                            [
                                'account_id'  => $bankAccount->id,
                                'description' => 'EOSB payment disbursed',
                                'debit'       => 0,
                                'credit'      => $amount,
                                'line_order'  => 1,
                            ],
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('EosbService: Failed to create journal entry for settlement payment.', [
                    'settlement_id' => $settlement->id,
                    'error'         => $e->getMessage(),
                ]);
            }

            return $settlement->fresh();
        });
    }

    private function resolvePolicy(Employee $employee): EosbPolicy
    {
        $countryCode = strtolower($employee->country_code ?? 'sa');

        $policy = EosbPolicy::where('organization_id', $employee->organization_id)
            ->where('is_active', true)
            ->where('country_code', $countryCode)
            ->first();

        if (!$policy) {
            throw new \InvalidArgumentException("No active EOSB policy found for country: {$countryCode}");
        }

        return $policy;
    }

    private function computeMonthlyDaysEarned(EosbPolicy $policy, int $totalMonths): float
    {
        $totalYears = bcdiv((string) $totalMonths, '12', 4);
        $firstPeriodYears = $policy->first_period_years;

        if ($totalYears <= $firstPeriodYears) {
            return round((float) $policy->first_period_days_per_year / 12, 4);
        }

        return round((float) $policy->subsequent_days_per_year / 12, 4);
    }

    private function computeTotalDaysEarned(EosbPolicy $policy, float $yearsOfService): float
    {
        $firstPeriodYears = (float) $policy->first_period_years;
        $firstPeriodDays = (float) $policy->first_period_days_per_year;
        $subsequentDays = (float) $policy->subsequent_days_per_year;

        if ($yearsOfService <= $firstPeriodYears) {
            return round($yearsOfService * $firstPeriodDays, 4);
        }

        $firstPeriodTotal = $firstPeriodYears * $firstPeriodDays;
        $remainingYears = $yearsOfService - $firstPeriodYears;
        $subsequentTotal = $remainingYears * $subsequentDays;

        return round($firstPeriodTotal + $subsequentTotal, 4);
    }
}
