<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\GosiConfiguration;
use App\Models\HR\GosiContribution;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GosiService
{
    /**
     * Calculate (or recalculate) the GOSI contribution for an employee for a given period.
     * Returns a persisted GosiContribution in 'draft' status.
     */
    public function calculateContributions(Employee $employee, int $year, int $month): GosiContribution
    {
        $countryCode = strtoupper($employee->country_code ?? 'SA');

        $config = GosiConfiguration::where('organization_id', $employee->organization_id)
            ->where('country_code', $countryCode)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->latest('effective_from')
            ->first();

        if (!$config) {
            throw new \InvalidArgumentException(
                "No active GOSI configuration found for country '{$countryCode}' in organization {$employee->organization_id}."
            );
        }

        $salary = $employee->currentSalary;
        if (!$salary) {
            throw new \InvalidArgumentException("Employee {$employee->id} has no active salary record for GOSI calculation.");
        }
        $basicSalary = (float) $salary->basic_salary;
        $totalSalary = (float) ($salary->gross_salary ?? $salary->basic_salary);

        $contributable = (string) $basicSalary;

        if ($config->salary_ceiling !== null) {
            if (bccomp($contributable, (string) $config->salary_ceiling, 4) > 0) {
                $contributable = (string) $config->salary_ceiling;
            }
        }

        if ($config->salary_floor !== null) {
            if (bccomp($contributable, (string) $config->salary_floor, 4) < 0) {
                $contributable = (string) $config->salary_floor;
            }
        }

        // Differentiate GCC nationals from expats for Saudi GOSI contribution rules
        $isGccNational = in_array(
            strtoupper((string) ($employee->nationality_code ?? $employee->nationality ?? '')),
            ['SA', 'AE', 'QA', 'OM', 'BH', 'KW'],
            true
        );

        if (!$isGccNational) {
            // Non-GCC expats: exempt from social insurance employee contribution;
            // employer pays occupational hazard contribution only (2% flat rate).
            $employeeContrib = '0.0000';
            $employerContrib = bcmul((string) $contributable, '0.02', 4);
            $hazardContrib   = '0.0000';
        } else {
            // GCC nationals: full GOSI rates apply (configured rates).
            $employeeContrib = bcmul((string) $contributable, bcdiv((string) $config->employee_contribution_pct, '100', 4), 4);
            $employerContrib = bcmul((string) $contributable, bcdiv((string) $config->employer_contribution_pct, '100', 4), 4);
            $hazardContrib   = bcmul((string) $contributable, bcdiv((string) $config->hazard_pct, '100', 4), 4);
        }

        $totalContrib = bcadd(bcadd((string) $employeeContrib, (string) $employerContrib, 4), (string) $hazardContrib, 4);

        return DB::transaction(function () use (
            $employee,
            $year,
            $month,
            $basicSalary,
            $totalSalary,
            $contributable,
            $employeeContrib,
            $employerContrib,
            $hazardContrib,
            $totalContrib
        ) {
            $contributionData = [
                'basic_salary' => $basicSalary,
                'total_salary' => $totalSalary,
                'contributable_salary' => $contributable,
                'employee_contribution' => $employeeContrib,
                'employer_contribution' => $employerContrib,
                'hazard_contribution' => $hazardContrib,
                'total_contribution' => $totalContrib,
                'status' => GosiContribution::STATUS_DRAFT,
            ];

            $contribution = GosiContribution::where('organization_id', $employee->organization_id)
                ->where('employee_id', $employee->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->lockForUpdate()
                ->first();

            if ($contribution) {
                $contribution->update($contributionData);
            } else {
                $contribution = GosiContribution::create(array_merge(
                    [
                        'organization_id' => $employee->organization_id,
                        'employee_id' => $employee->id,
                        'period_year' => $year,
                        'period_month' => $month,
                    ],
                    $contributionData
                ));
            }

            try {
                $orgId = $employee->organization_id;

                $expenseAccount = Account::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereIn('account_type', ['expense'])
                    ->where(function ($q) {
                        $q->where('name', 'like', '%gosi%')
                          ->orWhere('name', 'like', '%GOSI%')
                          ->orWhere('name', 'like', '%social insurance%')
                          ->orWhere('name', 'like', '%Social Insurance%');
                    })
                    ->first()
                    ?? Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->whereIn('account_type', ['expense'])
                        ->first();

                $payableAccount = Account::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->whereIn('account_type', ['liability'])
                    ->where(function ($q) {
                        $q->where('name', 'like', '%gosi%')
                          ->orWhere('name', 'like', '%GOSI%')
                          ->orWhere('name', 'like', '%payable%')
                          ->orWhere('name', 'like', '%social insurance%');
                    })
                    ->first()
                    ?? Account::withoutGlobalScopes()
                        ->where('organization_id', $orgId)
                        ->whereIn('account_type', ['liability'])
                        ->first();

                if ($expenseAccount === null || $payableAccount === null) {
                    Log::warning('GosiService: Missing GL accounts for GOSI journal entry.', [
                        'organization_id' => $orgId,
                        'employee_id'     => $employee->id,
                        'has_expense'     => $expenseAccount !== null,
                        'has_payable'     => $payableAccount !== null,
                    ]);
                } else {
                    $amount = (float) $totalContrib;
                    $entryDate = sprintf('%04d-%02d-01', $year, $month);
                    app(JournalService::class)->createEntry(
                        [
                            'organization_id' => $orgId,
                            'entry_date'      => $entryDate,
                            'reference'       => 'GOSI-' . $year . '-' . $month . '-EMP' . $employee->id,
                            'description'     => "GOSI contribution for employee {$employee->id} ({$year}/{$month})",
                            'source_type'     => GosiContribution::class,
                            'source_id'       => $contribution->id,
                        ],
                        [
                            [
                                'account_id'  => $expenseAccount->id,
                                'description' => 'GOSI expense',
                                'debit'       => $amount,
                                'credit'      => 0,
                                'line_order'  => 0,
                            ],
                            [
                                'account_id'  => $payableAccount->id,
                                'description' => 'GOSI payable',
                                'debit'       => 0,
                                'credit'      => $amount,
                                'line_order'  => 1,
                            ],
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('GosiService: Failed to create journal entry for GOSI contribution.', [
                    'employee_id'     => $employee->id,
                    'period'          => "{$year}/{$month}",
                    'error'           => $e->getMessage(),
                ]);
            }

            return $contribution;
        });
    }

    /**
     * Submit all draft GOSI contributions for an organization for a given period.
     * Marks each as 'submitted' with the current timestamp.
     */
    public function submitPeriod(Organization $org, int $year, int $month): void
    {
        DB::transaction(function () use ($org, $year, $month) {
            GosiContribution::where('organization_id', $org->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->where('status', GosiContribution::STATUS_DRAFT)
                ->update([
                    'status' => GosiContribution::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]);
        });
    }
}
