<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\SocialInsuranceSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates SIO (Social Insurance Organisation) export files for Bahrain.
 * Handles both nationals (SIO_NATIONALS) and expat (SIO_EXPATS) schemes.
 * Produces CSV for the SIO LMRA-linked employer portal.
 */
class BahrainSioExportService
{
    private const HEADERS = [
        'CPR Number',
        'Employee Name',
        'Bahraini / Expatriate',
        'SIO Registration No',
        'Insurable Salary (BHD)',
        'Employee Contribution (BHD)',
        'Employer Contribution (BHD)',
        'Work Injury Contribution (BHD)',
        'Total Contribution (BHD)',
        'Start Date',
    ];

    public function generateCsv(SocialInsuranceSubmission $submission): string
    {
        $submission->loadMissing(['lines.employee', 'lines.record']);

        $rows = [implode(',', self::HEADERS)];

        foreach ($submission->lines as $line) {
            $employee = $line->employee;
            $isBahraini = in_array($employee->nationality ?? '', ['BH'], true);

            $rows[] = implode(',', [
                $this->escape((string) ($employee->national_id ?? '')),
                $this->escape((string) ($employee->full_name ?? $employee->display_name ?? '')),
                $isBahraini ? 'Bahraini' : 'Expatriate',
                $this->escape((string) ($line->record?->employee_number_si ?? '')),
                number_format((float) $line->insurable_salary, 3, '.', ''),
                number_format((float) $line->employee_contribution, 3, '.', ''),
                number_format((float) $line->employer_contribution, 3, '.', ''),
                number_format((float) $line->work_hazard_contribution, 3, '.', ''),
                number_format((float) $line->total_contribution, 3, '.', ''),
                $line->record?->enrollment_date?->format('d/m/Y') ?? '',
            ]);
        }

        return implode("\n", $rows);
    }

    public function download(SocialInsuranceSubmission $submission): StreamedResponse
    {
        $filename = sprintf(
            'SIO_%d_%02d_%s.csv',
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
