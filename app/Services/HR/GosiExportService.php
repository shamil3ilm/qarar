<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\GosiContribution;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GOSI (General Organization for Social Insurance) contribution file export.
 *
 * Produces a CSV file containing one row per active employee who has a
 * GosiContribution record matching the payroll period's year/month.
 *
 * Column order mirrors the GOSI portal bulk-upload template:
 *   employee_id, employee_name, id_number, saudi_flag,
 *   basic_salary, housing_allowance, total_wage,
 *   employee_contribution, employer_contribution, total_contribution
 */
class GosiExportService
{
    private const CSV_HEADERS = [
        'employee_id',
        'employee_name',
        'id_number',
        'saudi_flag',
        'basic_salary',
        'housing_allowance',
        'total_wage',
        'employee_contribution',
        'employer_contribution',
        'total_contribution',
    ];

    // Nationalities considered Saudi for the saudi_flag column
    private const SAUDI_NATIONALITY_CODES = ['SA', 'SAU'];

    /**
     * Generate the CSV content string for a payroll period.
     *
     * Includes all active employees who have a GosiContribution record for
     * the period's year/month. Employees without a GosiContribution are
     * excluded (they are surfaced by validate() instead).
     */
    public function generate(PayrollPeriod $period): string
    {
        $contributions = $this->loadContributions($period);

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new \RuntimeException('Unable to open temporary stream for CSV generation.');
        }

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, self::CSV_HEADERS);

        foreach ($contributions as $contribution) {
            fputcsv($output, $this->buildRow($contribution));
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : '';
    }

    /**
     * Return a StreamedResponse that sends the GOSI CSV file as a download.
     */
    public function download(PayrollPeriod $period): StreamedResponse
    {
        $filename = sprintf(
            'GOSI_%s_%s.csv',
            $period->organization_id,
            $period->start_date->format('Ym')
        );

        return new StreamedResponse(function () use ($period): void {
            echo $this->generate($period);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Validate that all active employees for the period have the required
     * fields populated before generating the export file.
     *
     * @return array{
     *   valid: bool,
     *   total_employees: int,
     *   ready_count: int,
     *   warnings: list<array{employee_id: int|string, employee_number: string, name: string, issues: list<string>}>
     * }
     */
    public function validate(PayrollPeriod $period): array
    {
        $contributions = $this->loadContributions($period);

        // Find active employees in this org who have NO contribution record yet
        $employeesWithContribs = $contributions->pluck('employee_id')->all();

        $missingContribEmployees = Employee::where('organization_id', $period->organization_id)
            ->where('employment_status', Employee::STATUS_ACTIVE)
            ->whereNotIn('id', $employeesWithContribs)
            ->get();

        $warnings = [];

        // Warn about employees missing GOSI contributions entirely
        foreach ($missingContribEmployees as $employee) {
            $warnings[] = [
                'employee_id'     => $employee->id,
                'employee_number' => (string) ($employee->employee_number ?? ''),
                'name'            => $employee->display_name ?? trim("{$employee->first_name} {$employee->last_name}"),
                'issues'          => ['No GOSI contribution record found for this period.'],
            ];
        }

        // Validate employees who DO have contribution records
        foreach ($contributions as $contribution) {
            $employee = $contribution->employee;
            if (!$employee) {
                continue;
            }

            $issues = $this->detectIssues($employee, $contribution);
            if (!empty($issues)) {
                $warnings[] = [
                    'employee_id'     => $employee->id,
                    'employee_number' => (string) ($employee->employee_number ?? ''),
                    'name'            => $employee->display_name ?? trim("{$employee->first_name} {$employee->last_name}"),
                    'issues'          => $issues,
                ];
            }
        }

        $totalEmployees = $contributions->count() + $missingContribEmployees->count();
        $readyCount     = $contributions->filter(function (GosiContribution $c) {
            $employee = $c->employee;
            return $employee && empty($this->detectIssues($employee, $c));
        })->count();

        return [
            'valid'           => empty($warnings),
            'total_employees' => $totalEmployees,
            'ready_count'     => $readyCount,
            'warnings'        => $warnings,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadContributions(PayrollPeriod $period): Collection
    {
        return GosiContribution::with('employee')
            ->where('organization_id', $period->organization_id)
            ->where('period_year', (int) $period->start_date->format('Y'))
            ->where('period_month', (int) $period->start_date->format('n'))
            ->get();
    }

    /**
     * Build a single CSV data row from a GosiContribution and its employee.
     */
    private function buildRow(GosiContribution $contribution): array
    {
        $employee = $contribution->employee;

        $name = $employee
            ? ($employee->display_name ?? trim("{$employee->first_name} {$employee->last_name}"))
            : '';

        // National ID / Iqama number (encrypted field on Employee)
        $idNumber = $employee?->national_id ?? '';

        $saudiFlag = $this->isSaudi($employee) ? 1 : 0;

        return [
            $employee?->employee_number ?? '',
            $name,
            (string) $idNumber,
            $saudiFlag,
            number_format((float) $contribution->basic_salary, 2, '.', ''),
            number_format((float) ($contribution->housing_allowance ?? 0), 2, '.', ''),
            number_format((float) ($contribution->total_salary ?? $contribution->basic_salary), 2, '.', ''),
            number_format((float) $contribution->employee_contribution, 2, '.', ''),
            number_format((float) $contribution->employer_contribution, 2, '.', ''),
            number_format((float) $contribution->total_contribution, 2, '.', ''),
        ];
    }

    /**
     * Detect missing/invalid fields on an employee for GOSI export.
     *
     * @return list<string>
     */
    private function detectIssues(Employee $employee, GosiContribution $contribution): array
    {
        $issues = [];

        if (empty($employee->national_id)) {
            $issues[] = 'Missing national ID / Iqama number.';
        }

        if (empty($employee->employee_number)) {
            $issues[] = 'Missing employee number.';
        }

        if ((float) $contribution->basic_salary <= 0) {
            $issues[] = 'Basic salary is zero or not set.';
        }

        if ((float) $contribution->employee_contribution <= 0 && $this->isSaudi($employee)) {
            $issues[] = 'Saudi employee has zero employee contribution.';
        }

        if ((float) $contribution->employer_contribution <= 0) {
            $issues[] = 'Employer contribution is zero or not set.';
        }

        return $issues;
    }

    private function isSaudi(?Employee $employee): bool
    {
        if (!$employee) {
            return false;
        }

        $code = strtoupper(trim((string) ($employee->nationality ?? '')));

        return in_array($code, self::SAUDI_NATIONALITY_CODES, true);
    }
}
