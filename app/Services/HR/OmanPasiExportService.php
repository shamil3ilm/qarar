<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\SocialInsuranceSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates PASI (Public Authority for Social Insurance) export files for Oman.
 * Produces CSV accepted by the PASI employer portal for monthly contribution uploads.
 */
class OmanPasiExportService
{
    private const HEADERS = [
        'Employee Number',
        'Civil ID',
        'Employee Name (Arabic)',
        'Employee Name (English)',
        'Basic Salary (OMR)',
        'Insurable Salary (OMR)',
        'Employee Contribution 7% (OMR)',
        'Employer Contribution 10.5% (OMR)',
        'Work Injury Contribution 1% (OMR)',
        'Total Contribution (OMR)',
        'Nationality',
        'Enrollment Date',
    ];

    public function generateCsv(SocialInsuranceSubmission $submission): string
    {
        $submission->loadMissing(['lines.employee', 'lines.record']);

        $rows = [implode(',', self::HEADERS)];

        foreach ($submission->lines as $line) {
            $employee = $line->employee;
            $rows[] = implode(',', [
                $this->escape((string) ($employee->employee_number ?? '')),
                $this->escape((string) ($employee->national_id ?? '')),
                $this->escape((string) ($employee->name_arabic ?? '')),
                $this->escape((string) ($employee->full_name ?? $employee->display_name ?? '')),
                number_format((float) $line->insurable_salary, 3, '.', ''),
                number_format((float) $line->insurable_salary, 3, '.', ''),
                number_format((float) $line->employee_contribution, 3, '.', ''),
                number_format((float) $line->employer_contribution, 3, '.', ''),
                number_format((float) $line->work_hazard_contribution, 3, '.', ''),
                number_format((float) $line->total_contribution, 3, '.', ''),
                $this->escape((string) ($employee->nationality ?? '')),
                $line->record?->enrollment_date?->format('d/m/Y') ?? '',
            ]);
        }

        return implode("\n", $rows);
    }

    public function download(SocialInsuranceSubmission $submission): StreamedResponse
    {
        $filename = sprintf(
            'PASI_%d_%02d_%s.csv',
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
