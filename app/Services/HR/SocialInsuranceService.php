<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\SocialInsuranceRecord;
use App\Models\HR\SocialInsuranceScheme;
use App\Models\HR\SocialInsuranceSubmission;
use App\Models\HR\SocialInsuranceSubmissionLine;
use Illuminate\Support\Facades\DB;

class SocialInsuranceService
{
    /**
     * Calculate contributions for a single employee and scheme.
     */
    public function calculateContributions(Employee $employee, SocialInsuranceScheme $scheme): array
    {
        $record = SocialInsuranceRecord::where('employee_id', $employee->id)
            ->where('scheme_id', $scheme->id)
            ->where('status', SocialInsuranceRecord::STATUS_ACTIVE)
            ->first();

        if (!$record) {
            return [
                'enrolled' => false,
                'employee_contribution' => 0,
                'employer_contribution' => 0,
                'work_hazard_contribution' => 0,
                'total_contribution' => 0,
            ];
        }

        $insurable = $record->insurable_salary ?? ($employee->currentSalary?->basic_salary ?? 0);

        if ($scheme->salary_ceiling && $insurable > $scheme->salary_ceiling) {
            $insurable = (float) $scheme->salary_ceiling;
        }

        if ($scheme->salary_floor && $insurable < $scheme->salary_floor) {
            $insurable = (float) $scheme->salary_floor;
        }

        $employeeContrib = bcmul(
            (string) $insurable,
            bcdiv((string) $scheme->employee_contribution_pct, '100', 6),
            4
        );
        $employerContrib = bcmul(
            (string) $insurable,
            bcdiv((string) $scheme->employer_contribution_pct, '100', 6),
            4
        );
        $workHazardContrib = bcmul(
            (string) $insurable,
            bcdiv((string) ($scheme->work_hazard_pct ?? '0'), '100', 6),
            4
        );
        $total = bcadd(bcadd($employeeContrib, $employerContrib, 4), $workHazardContrib, 4);

        return [
            'enrolled' => true,
            'record_id' => $record->id,
            'employee_number_si' => $record->employee_number_si,
            'insurable_salary' => $insurable,
            'employee_contribution' => $employeeContrib,
            'employer_contribution' => $employerContrib,
            'work_hazard_contribution' => $workHazardContrib,
            'total_contribution' => $total,
        ];
    }

    /**
     * Generate the monthly submission for an organization and scheme.
     */
    public function generateMonthlySubmission(
        Organization $organization,
        SocialInsuranceScheme $scheme,
        int $year,
        int $month
    ): SocialInsuranceSubmission {
        $existing = SocialInsuranceSubmission::where('organization_id', $organization->id)
            ->where('scheme_id', $scheme->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereNot('status', SocialInsuranceSubmission::STATUS_REJECTED)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException("Submission already exists for this period.");
        }

        return DB::transaction(function () use ($organization, $scheme, $year, $month) {
            $submission = SocialInsuranceSubmission::create([
                'organization_id' => $organization->id,
                'scheme_id' => $scheme->id,
                'period_year' => $year,
                'period_month' => $month,
                'status' => SocialInsuranceSubmission::STATUS_DRAFT,
            ]);

            $activeRecords = SocialInsuranceRecord::where('organization_id', $organization->id)
                ->where('scheme_id', $scheme->id)
                ->where('status', SocialInsuranceRecord::STATUS_ACTIVE)
                ->with('employee')
                ->get();

            $totals = [
                'total_employees' => 0,
                'total_insurable_salary' => 0,
                'total_employee_contrib' => 0,
                'total_employer_contrib' => 0,
                'total_work_hazard_contrib' => 0,
                'total_amount' => 0,
            ];

            foreach ($activeRecords as $record) {
                $employee = $record->employee;
                if (!$employee) {
                    continue;
                }

                $contributions = $this->calculateContributions($employee, $scheme);

                if (!$contributions['enrolled']) {
                    continue;
                }

                SocialInsuranceSubmissionLine::create([
                    'submission_id' => $submission->id,
                    'employee_id' => $employee->id,
                    'record_id' => $record->id,
                    'employee_number_si' => $contributions['employee_number_si'],
                    'insurable_salary' => $contributions['insurable_salary'],
                    'employee_contribution' => $contributions['employee_contribution'],
                    'employer_contribution' => $contributions['employer_contribution'],
                    'work_hazard_contribution' => $contributions['work_hazard_contribution'],
                    'total_contribution' => $contributions['total_contribution'],
                ]);

                $totals['total_employees']++;
                $totals['total_insurable_salary'] = bcadd((string) $totals['total_insurable_salary'], (string) $contributions['insurable_salary'], 4);
                $totals['total_employee_contrib'] = bcadd((string) $totals['total_employee_contrib'], (string) $contributions['employee_contribution'], 4);
                $totals['total_employer_contrib'] = bcadd((string) $totals['total_employer_contrib'], (string) $contributions['employer_contribution'], 4);
                $totals['total_work_hazard_contrib'] = bcadd((string) $totals['total_work_hazard_contrib'], (string) $contributions['work_hazard_contribution'], 4);
                $totals['total_amount'] = bcadd((string) $totals['total_amount'], (string) $contributions['total_contribution'], 4);
            }

            $submission->update($totals);

            return $submission->fresh();
        });
    }

    /**
     * Mark a submission as submitted.
     */
    public function submitSubmission(SocialInsuranceSubmission $submission, ?string $referenceNumber = null): SocialInsuranceSubmission
    {
        if (!$submission->canBeSubmitted()) {
            throw new \InvalidArgumentException('Only draft submissions can be submitted.');
        }

        $submission->update([
            'status' => SocialInsuranceSubmission::STATUS_SUBMITTED,
            'reference_number' => $referenceNumber,
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return $submission->fresh();
    }

    /**
     * Enroll an employee in a social insurance scheme.
     */
    public function enrollEmployee(Employee $employee, SocialInsuranceScheme $scheme, array $data): SocialInsuranceRecord
    {
        $existing = SocialInsuranceRecord::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('scheme_id', $scheme->id)
            ->first();

        if ($existing && !$existing->trashed()) {
            throw new \InvalidArgumentException('Employee is already enrolled in this scheme.');
        }

        return SocialInsuranceRecord::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'scheme_id' => $scheme->id,
            'employee_number_si' => $data['employee_number_si'] ?? null,
            'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
            'insurable_salary' => $data['insurable_salary'] ?? null,
            'status' => SocialInsuranceRecord::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
