<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\SocialInsuranceSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates GPSSA (General Pension & Social Security Authority) export files for UAE.
 * Applies to UAE nationals only. Produces CSV for the GPSSA employer portal.
 */
class UaeGpssaExportService
{
    private const HEADERS = [
        'Emirates ID',
        'GPSSA Registration No',
        'Employee Name',
        'Insurable Salary (AED)',
        'Employee Contribution 5% (AED)',
        'Employer Contribution 12.5% (AED)',
        'Total Contribution (AED)',
        'Joining Date',
    ];

    public function generateCsv(SocialInsuranceSubmission $submission): string
    {
        $submission->loadMissing(['lines.employee', 'lines.record']);

        $rows = [implode(',', self::HEADERS)];

        foreach ($submission->lines as $line) {
            $employee = $line->employee;

            $rows[] = implode(',', [
                $this->escape((string) ($employee->national_id ?? '')),
                $this->escape((string) ($line->record?->employee_number_si ?? '')),
                $this->escape((string) ($employee->full_name ?? $employee->display_name ?? '')),
                number_format((float) $line->insurable_salary, 3, '.', ''),
                number_format((float) $line->employee_contribution, 3, '.', ''),
                number_format((float) $line->employer_contribution, 3, '.', ''),
                number_format((float) $line->total_contribution, 3, '.', ''),
                $line->record?->enrollment_date?->format('d/m/Y') ?? '',
            ]);
        }

        return implode("\n", $rows);
    }

    public function download(SocialInsuranceSubmission $submission): StreamedResponse
    {
        $filename = sprintf(
            'GPSSA_%d_%02d_%s.csv',
            $submission->period_year,
            $submission->period_month,
            now()->format('Ymd')
        );

        return response()->streamDownload(
            fn () => print($this->generateCsv($submission)),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function escape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
