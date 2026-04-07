<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\SocialInsuranceSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates PIFSS (Public Institution for Social Security) export files for Kuwait.
 * Produces CSV for the PIFSS employer portal. Applies to Kuwaiti nationals only.
 */
class KuwaitPifssExportService
{
    private const HEADERS = [
        'Civil File No',
        'Employee Name',
        'Kuwaiti / Non-Kuwaiti',
        'PIFSS Registration No',
        'Basic Salary (KWD)',
        'Insurable Salary (KWD)',
        'Employee Contribution 7.5% (KWD)',
        'Employer Contribution 11.5% (KWD)',
        'Total Contribution (KWD)',
        'Employment Date',
    ];

    public function generateCsv(SocialInsuranceSubmission $submission): string
    {
        $submission->loadMissing(['lines.employee', 'lines.record']);

        $rows = [implode(',', self::HEADERS)];

        foreach ($submission->lines as $line) {
            $employee = $line->employee;
            $isNational = in_array($employee->nationality ?? '', ['KW'], true);

            $rows[] = implode(',', [
                $this->escape((string) ($employee->national_id ?? '')),
                $this->escape((string) ($employee->full_name ?? $employee->display_name ?? '')),
                $isNational ? 'Kuwaiti' : 'Non-Kuwaiti',
                $this->escape((string) ($line->record?->employee_number_si ?? '')),
                number_format((float) $line->insurable_salary, 3, '.', ''),
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
            'PIFSS_%d_%02d_%s.csv',
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
