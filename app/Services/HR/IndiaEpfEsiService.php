<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\EpfContribution;
use App\Models\HR\EsiContribution;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\ProfessionalTaxConfig;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * India statutory payroll compliance service.
 *
 * Calculates and exports:
 *  - EPF (Employees' Provident Fund) — 12% employee + 12% employer (split into EPS + EPF diff)
 *  - ESI (Employees' State Insurance) — 0.75% employee + 3.25% employer (applicable ≤ ₹21,000 gross)
 *  - PT  (Professional Tax) — state-wise slab-based monthly tax
 *
 * References:
 *  - EPF: Employees' Provident Funds and Misc Provisions Act, 1952
 *  - ESI: Employees' State Insurance Act, 1948 (amended rates effective Apr 2019)
 *  - PT: Varies by Indian state; static fallback slabs for common states included.
 */
class IndiaEpfEsiService
{
    // ── EPF constants ────────────────────────────────────────────────────────
    /** Statutory PF wage ceiling: contributions computed on min(basic+DA, 15000) */
    private const EPF_WAGE_CEILING = 15000.00;

    /** EPS wage ceiling: same as EPF ceiling for most employees */
    private const EPS_WAGE_CEILING = 15000.00;

    /** Employee EPF contribution rate (%) */
    private const EMPLOYEE_RATE = '12.00';

    /** Employer EPS contribution rate (%) — Employee Pension Scheme */
    private const EPS_RATE = '8.33';

    /** Employer EPF diff (12% − EPS = 3.67%) */
    private const EPF_EMPLOYER_DIFF_RATE = '3.67';

    /** EDLI employer contribution rate (%) — Employee Deposit Linked Insurance */
    private const EDLI_RATE = '0.50';

    /** Admin charges (%) */
    private const ADMIN_RATE = '0.50';

    /** Maximum monthly EPS contribution (8.33% × 15000 ≈ ₹1249.50, rounded to ₹1250) */
    private const EPS_MAX = 1250.00;

    // ── ESI constants ────────────────────────────────────────────────────────
    /** Monthly gross ceiling above which ESI is not applicable */
    private const ESI_GROSS_CEILING = 21000.00;

    /** Employee ESI rate (%) — effective April 2020 */
    private const ESI_EMPLOYEE_RATE = '0.75';

    /** Employer ESI rate (%) — effective April 2020 */
    private const ESI_EMPLOYER_RATE = '3.25';

    // ── Professional Tax static fallback slabs ───────────────────────────────
    /**
     * Default PT slabs for common Indian states.
     * Format: [salary_from, salary_to|null, monthly_tax]
     * Configurable overrides via ProfessionalTaxConfig model.
     *
     * @var array<string, array<int, array{float, float|null, float}>>
     */
    private const DEFAULT_PT_SLABS = [
        // Karnataka
        'KA' => [[0, 14999, 0], [15000, null, 200]],
        // Maharashtra
        'MH' => [[0, 7499, 0], [7500, 9999, 175], [10000, null, 200]],
        // West Bengal
        'WB' => [[0, 8500, 0], [8501, 10000, 90], [10001, 15000, 110], [15001, 25000, 130], [25001, 40000, 150], [40001, null, 200]],
        // Tamil Nadu
        'TN' => [[0, 3499, 0], [3500, 4999, 28.57], [5000, 7499, 46.42], [7500, 9999, 57.14], [10000, 12499, 67.85], [12500, null, 208.33]],
        // Andhra Pradesh / Telangana
        'AP' => [[0, 14999, 0], [15000, 19999, 150], [20000, null, 200]],
        'TS' => [[0, 14999, 0], [15000, 19999, 150], [20000, null, 200]],
        // Gujarat
        'GJ' => [[0, 5999, 0], [6000, null, 200]],
        // Odisha
        'OD' => [[0, 5000, 0], [5001, 6000, 25], [6001, 8000, 40], [8001, 10000, 60], [10001, 15000, 90], [15001, 20000, 125], [20001, null, 200]],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate EPF contributions for one employee's monthly salary.
     *
     * @param  float $basicSalary  Basic salary + DA (PF-eligible component)
     * @return array{
     *   pf_wage: string,
     *   employee_contribution: string,
     *   employer_eps_contribution: string,
     *   employer_epf_contribution: string,
     *   edli_contribution: string,
     *   admin_charges: string,
     *   employer_contribution: string,
     * }
     */
    public function calculateEpf(float $basicSalary): array
    {
        $pfWage = min($basicSalary, self::EPF_WAGE_CEILING);

        $employeeContrib = bcdiv(bcmul((string) $pfWage, self::EMPLOYEE_RATE, 6), '100', 2);

        $epsWage  = min($pfWage, self::EPS_WAGE_CEILING);
        $epsRaw   = bcdiv(bcmul((string) $epsWage, self::EPS_RATE, 6), '100', 2);
        $eps      = number_format(min((float) $epsRaw, self::EPS_MAX), 2, '.', '');

        $epfDiff  = bcsub($employeeContrib, $eps, 2);
        $edli     = bcdiv(bcmul((string) $pfWage, self::EDLI_RATE, 6), '100', 2);
        $admin    = bcdiv(bcmul((string) $pfWage, self::ADMIN_RATE, 6), '100', 2);

        return [
            'pf_wage'                   => number_format($pfWage, 2, '.', ''),
            'employee_contribution'     => $employeeContrib,
            'employer_eps_contribution' => $eps,
            'employer_epf_contribution' => $epfDiff,
            'edli_contribution'         => $edli,
            'admin_charges'             => $admin,
            'employer_contribution'     => $employeeContrib, // mirrors employee 12%
        ];
    }

    /**
     * Calculate ESI contributions for one employee's monthly gross salary.
     *
     * @param  float $grossSalary Monthly gross salary
     * @return array{
     *   gross_wage: string,
     *   employee_contribution: string,
     *   employer_contribution: string,
     *   is_applicable: bool,
     * }
     */
    public function calculateEsi(float $grossSalary): array
    {
        if ($grossSalary > self::ESI_GROSS_CEILING) {
            return [
                'gross_wage'            => number_format($grossSalary, 2, '.', ''),
                'employee_contribution' => '0.00',
                'employer_contribution' => '0.00',
                'is_applicable'         => false,
            ];
        }

        $employee = bcdiv(bcmul((string) $grossSalary, self::ESI_EMPLOYEE_RATE, 6), '100', 2);
        $employer = bcdiv(bcmul((string) $grossSalary, self::ESI_EMPLOYER_RATE, 6), '100', 2);

        return [
            'gross_wage'            => number_format($grossSalary, 2, '.', ''),
            'employee_contribution' => $employee,
            'employer_contribution' => $employer,
            'is_applicable'         => true,
        ];
    }

    /**
     * Calculate Professional Tax for one employee.
     * Checks database-configured slabs first; falls back to DEFAULT_PT_SLABS.
     *
     * @param  float  $grossSalary Monthly gross salary
     * @param  string $stateCode   Two-letter Indian state code (e.g. "KA", "MH")
     * @param  int    $orgId       Organization ID for DB slab lookup
     */
    public function calculatePt(float $grossSalary, string $stateCode, int $orgId = 0): string
    {
        $state = strtoupper($stateCode);

        // Prefer database-configured slabs
        if ($orgId > 0) {
            $dbTax = $this->lookupPtFromDb($grossSalary, $state, $orgId);
            if ($dbTax !== null) {
                return $dbTax;
            }
        }

        // Fallback to static slabs
        $slabs = self::DEFAULT_PT_SLABS[$state] ?? [];

        foreach ($slabs as [$from, $to, $tax]) {
            if ($grossSalary >= $from && ($to === null || $grossSalary <= $to)) {
                return number_format((float) $tax, 2, '.', '');
            }
        }

        return '0.00';
    }

    /**
     * Generate the EPFO ECR (Electronic Challan cum Return) text file.
     *
     * ECR format per EPFO UAN portal specification:
     * UAN#~#Member Name#~#Gross Wages#~#EPF Wages#~#EPS Wages#~#EPF Contribution
     *   #~#EPS Contribution#~#EPF+EPS#~#LOP Days#~#Refund of Advances#~#Arrear Wages
     *
     * The header line is a single "#~#" separator.
     */
    public function generateEcr(PayrollPeriod $period): string
    {
        $contributions = EpfContribution::where('payroll_period_id', $period->id)
            ->with('employee')
            ->get();

        $rows = ['#~#'];  // ECR header

        foreach ($contributions as $c) {
            $rows[] = implode('#~#', [
                (string) ($c->uan ?? ''),
                (string) ($c->employee->full_name ?? $c->employee->display_name ?? ''),
                (string) (int) round((float) $c->pf_wage),
                (string) (int) round((float) $c->pf_wage),  // EPF wage
                (string) (int) round((float) $c->pf_wage),  // EPS wage (same when ≤ ceiling)
                (string) (int) round((float) $c->employee_contribution),
                (string) (int) round((float) $c->employer_eps_contribution),
                (string) (int) round((float) $c->employee_contribution + (float) $c->employer_eps_contribution),
                '0',   // NCP days
                '0',   // Refund of advances
                '0',   // Arrear wages
            ]);
        }

        return implode("\r\n", $rows);
    }

    /**
     * Download the ECR file as a StreamedResponse.
     */
    public function downloadEcr(PayrollPeriod $period): StreamedResponse
    {
        $filename = sprintf(
            'ECR_%d%02d_%s.txt',
            $period->period_year ?? $period->start_date->year,
            $period->period_month ?? $period->start_date->month,
            now()->format('Ymd')
        );

        return response()->streamDownload(
            fn () => print($this->generateEcr($period)),
            $filename,
            ['Content-Type' => 'text/plain']
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function lookupPtFromDb(float $grossSalary, string $state, int $orgId): ?string
    {
        $slab = ProfessionalTaxConfig::where('organization_id', $orgId)
            ->forState($state)
            ->where('salary_from', '<=', $grossSalary)
            ->where(function ($q) use ($grossSalary) {
                $q->whereNull('salary_to')
                    ->orWhere('salary_to', '>=', $grossSalary);
            })
            ->first();

        if ($slab === null) {
            return null;
        }

        return number_format((float) $slab->monthly_tax, 2, '.', '');
    }
}
