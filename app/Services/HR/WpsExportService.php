<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WPS (Wage Protection System) SIF (Salary Information File) export service.
 *
 * Generates a fixed-width text file conforming to the UAE/Saudi WPS SIF format
 * with one EDR (Employer Detail Record) at the top and one SDR (Salary Detail
 * Record) per paid employee.
 *
 * Field widths are right-padded with spaces for alpha fields and left-padded
 * (zero-filled) for numeric fields, as per the WPS SIF spec.
 */
class WpsExportService
{
    private const EDR_RECORD_TYPE     = 'EDR';
    private const SDR_RECORD_TYPE     = 'SDR';
    private const SALARY_FREQUENCY    = 'M'; // Monthly

    // EDR field lengths
    private const EDR_RECORD_TYPE_LEN  = 3;
    private const EDR_EMPLOYER_ID_LEN  = 20;
    private const EDR_ROUTING_CODE_LEN = 9;
    private const EDR_SALARY_MONTH_LEN = 6;
    private const EDR_TOTAL_SALARY_LEN = 18;
    private const EDR_TOTAL_COUNT_LEN  = 6;

    // SDR field lengths
    private const SDR_RECORD_TYPE_LEN  = 3;
    private const SDR_EMPLOYEE_ID_LEN  = 20;
    private const SDR_AGENT_BANK_LEN   = 4;
    private const SDR_IBAN_LEN         = 23;
    private const SDR_SALARY_LEN       = 18;
    private const SDR_FREQUENCY_LEN    = 1;
    private const SDR_START_DATE_LEN   = 8;

    /**
     * Generate the raw SIF file content for a payroll period.
     *
     * Only PAID payslips are included. Employees without a valid IBAN are
     * silently skipped (use validate() before downloading to surface warnings).
     *
     * @param  PayrollPeriod $period   The payroll period to export.
     * @param  string        $bankCode The employer's bank routing code (9 chars).
     * @return string                  Raw fixed-width SIF file content.
     */
    public function generate(PayrollPeriod $period, string $bankCode): string
    {
        $organization = Organization::findOrFail($period->organization_id);

        $payslips = $this->loadPaidPayslips($period);

        // Filter to employees with a valid IBAN
        $payslips = $payslips->filter(
            fn(Payslip $p) => !empty($p->employee->bank_iban)
        );

        $totalCount    = $payslips->count();
        $totalAmountFils = $payslips->sum(
            fn(Payslip $p) => $this->toFils((float) $p->net_salary)
        );

        $salaryMonth = $period->start_date->format('Ym');

        $lines   = [];
        $lines[] = $this->buildEdr(
            organization: $organization,
            bankCode: $bankCode,
            salaryMonth: $salaryMonth,
            totalFils: $totalAmountFils,
            totalCount: $totalCount,
        );

        foreach ($payslips as $payslip) {
            $lines[] = $this->buildSdr($payslip);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Return a StreamedResponse that sends the SIF file as a download.
     */
    public function download(PayrollPeriod $period, string $bankCode): StreamedResponse
    {
        $filename = sprintf(
            'WPS_%s_%s.txt',
            $period->organization_id,
            $period->start_date->format('Ym')
        );

        return new StreamedResponse(function () use ($period, $bankCode): void {
            echo $this->generate($period, $bankCode);
        }, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Return summary statistics for a period without generating the full file.
     *
     * @return array{
     *   total_employees: int,
     *   total_amount: float,
     *   currency: string,
     *   missing_iban: int,
     *   ready_count: int,
     * }
     */
    public function getStats(PayrollPeriod $period): array
    {
        $payslips = $this->loadPaidPayslips($period);

        $missingIban = $payslips->filter(
            fn(Payslip $p) => empty($p->employee->bank_iban)
        )->count();

        $readyPayslips = $payslips->filter(
            fn(Payslip $p) => !empty($p->employee->bank_iban)
        );

        // Determine the dominant currency (fallback to org base currency)
        $currency = $readyPayslips->first()?->currency_code
            ?? $payslips->first()?->currency_code
            ?? 'AED';

        return [
            'total_employees' => $payslips->count(),
            'ready_count'     => $readyPayslips->count(),
            'missing_iban'    => $missingIban,
            'total_amount'    => round((float) $readyPayslips->sum('net_salary'), 2),
            'currency'        => $currency,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function loadPaidPayslips(PayrollPeriod $period): Collection
    {
        return Payslip::with('employee')
            ->where('payroll_period_id', $period->id)
            ->where('status', Payslip::STATUS_PAID)
            ->get();
    }

    /**
     * Build a single EDR line.
     */
    private function buildEdr(
        Organization $organization,
        string $bankCode,
        string $salaryMonth,
        int $totalFils,
        int $totalCount
    ): string {
        // Employer ID: use tax_number as the CRN / trade-license identifier.
        // Decrypt it (cast 'encrypted') and fall back to org slug.
        $employerId = $organization->tax_number ?? $organization->slug ?? '';

        return
            $this->padRight(self::EDR_RECORD_TYPE, self::EDR_RECORD_TYPE_LEN)
            . $this->padRight($employerId, self::EDR_EMPLOYER_ID_LEN)
            . $this->padRight($bankCode, self::EDR_ROUTING_CODE_LEN)
            . $this->padRight($salaryMonth, self::EDR_SALARY_MONTH_LEN)
            . $this->padLeft((string) $totalFils, self::EDR_TOTAL_SALARY_LEN)
            . $this->padLeft((string) $totalCount, self::EDR_TOTAL_COUNT_LEN);
    }

    /**
     * Build a single SDR line for one payslip.
     */
    private function buildSdr(Payslip $payslip): string
    {
        $employee  = $payslip->employee;
        $iban      = (string) ($employee->bank_iban ?? '');
        $agentBank = $this->extractAgentBank($iban);
        $salaryFils = $this->toFils((float) $payslip->net_salary);

        // Start date: employee joining date in YYYYMMDD, fallback to period start
        $startDate = ($employee->joining_date ?? $payslip->payrollPeriod?->start_date)
            ?->format('Ymd') ?? '';

        return
            $this->padRight(self::SDR_RECORD_TYPE, self::SDR_RECORD_TYPE_LEN)
            . $this->padRight((string) ($employee->employee_number ?? ''), self::SDR_EMPLOYEE_ID_LEN)
            . $this->padRight($agentBank, self::SDR_AGENT_BANK_LEN)
            . $this->padRight($iban, self::SDR_IBAN_LEN)
            . $this->padLeft((string) $salaryFils, self::SDR_SALARY_LEN)
            . self::SALARY_FREQUENCY
            . $this->padRight($startDate, self::SDR_START_DATE_LEN);
    }

    /**
     * Extract the 4-character agent bank code from an IBAN.
     *
     * UAE IBANs are structured: AE + 2 check digits + 3-char bank code + account.
     * The WPS spec requests the 4 chars immediately after the country code "AE",
     * which is positions 2–5 (0-indexed), i.e. the 2 check digits + first 2 of bank.
     * We simply take chars 2–5 of the IBAN (after removing spaces).
     */
    private function extractAgentBank(string $iban): string
    {
        $clean = strtoupper(str_replace(' ', '', $iban));

        // For UAE IBANs starting with AE: take chars at index 2..5 (4 chars)
        if (str_starts_with($clean, 'AE') && strlen($clean) >= 6) {
            return substr($clean, 2, 4);
        }

        // For Saudi IBANs starting with SA: take chars at index 2..5
        if (str_starts_with($clean, 'SA') && strlen($clean) >= 6) {
            return substr($clean, 2, 4);
        }

        // Fallback: return first 4 chars or empty padded
        return substr($clean, 0, 4);
    }

    /**
     * Convert a decimal salary amount to integer fils (smallest unit, 2 decimals).
     * e.g. 5000.75 AED => 500075
     */
    private function toFils(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Right-pad a string to the given length (truncate if longer).
     */
    private function padRight(string $value, int $length): string
    {
        return str_pad(substr($value, 0, $length), $length, ' ', STR_PAD_RIGHT);
    }

    /**
     * Left-pad a numeric string with zeros to the given length (truncate if longer).
     */
    private function padLeft(string $value, int $length): string
    {
        return str_pad(substr($value, 0, $length), $length, '0', STR_PAD_LEFT);
    }
}
