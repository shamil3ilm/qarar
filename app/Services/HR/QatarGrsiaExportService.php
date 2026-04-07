<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\SocialInsuranceSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates GRSIA (General Retirement & Social Insurance Authority) export files for Qatar.
 * Produces CSV for the GRSIA employer portal. Applies to Qatari nationals only.
 */
class QatarGrsiaExportService
{
    private const HEADERS = [
        'QID (Qatar ID)',
        'GRSIA File Number',
        'Employee Name (Arabic)',
        'Employee Name (English)',
        'Insurable Salary (QAR)',
        'Employee Contribution 5% (QAR)',
        'Employer Contribution 10% (QAR)',
        'Total Contribution (QAR)',
        'Enrollment Date',
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
                $this->escape((string) ($employee->name_arabic ?? '')),
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
            'GRSIA_%d_%02d_%s.csv',
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
