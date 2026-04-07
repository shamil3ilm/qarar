<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\TdsCertificate;
use App\Models\Tax\TdsDeduction;
use App\Models\Tax\TdsReturn;
use App\Models\Tax\TdsSection;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;

class TdsService
{
    /**
     * Calculate TDS amount for a given payment.
     */
    public function calculateTds(
        string $deducteeType,
        float $paymentAmount,
        string $sectionCode,
        bool $hasPan = true
    ): array {
        $section = TdsSection::where('section_code', $sectionCode)
            ->where('is_active', true)
            ->firstOrFail();

        // No TDS if below threshold
        if (bccomp((string) $paymentAmount, (string) $section->threshold_amount, 4) <= 0) {
            return [
                'section_code'    => $sectionCode,
                'payment_amount'  => $paymentAmount,
                'tds_rate'        => 0,
                'tds_amount'      => 0,
                'surcharge'       => 0,
                'education_cess'  => 0,
                'net_tds'         => 0,
                'below_threshold' => true,
            ];
        }

        $rate = $hasPan
            ? $section->getRateForDeducteeType($deducteeType)
            : (float) $section->rate_no_pan;

        $tdsAmount    = (float) bcmul((string) $paymentAmount, bcdiv((string) $rate, '100', 6), 4);
        $surcharge    = 0.0;
        $cessRate     = config('erp.tds_cess_rate', '0.04');
        $educationCss = (float) bcmul((string) $tdsAmount, (string) $cessRate, 4); // health & education cess
        $netTds       = (float) bcadd(bcadd((string) $tdsAmount, (string) $surcharge, 4), (string) $educationCss, 4);

        return [
            'section_code'    => $sectionCode,
            'section_id'      => $section->id,
            'payment_amount'  => $paymentAmount,
            'tds_rate'        => $rate,
            'tds_amount'      => $tdsAmount,
            'surcharge'       => $surcharge,
            'education_cess'  => $educationCss,
            'net_tds'         => $netTds,
            'below_threshold' => false,
        ];
    }

    /**
     * Record a TDS deduction entry.
     */
    public function recordDeduction(array $data): TdsDeduction
    {
        $existing = TdsDeduction::where('organization_id', $data['organization_id'])
            ->where('payment_reference', $data['payment_reference'])
            ->where('section_id', $data['section_id'] ?? null)
            ->first();

        if ($existing) {
            return $existing; // idempotent
        }

        $paymentDate = $data['payment_date'] ?? now()->toDateString();
        $quarter     = $this->getQuarterForDate($paymentDate);
        $year        = $this->getFinancialYearForDate($paymentDate);

        return TdsDeduction::create(array_merge($data, [
            'period_quarter' => $quarter,
            'period_year'    => $year,
        ]));
    }

    /**
     * Generate a TDS certificate (Form 16A) for a deductee for a quarter.
     */
    public function generateCertificate(
        Organization $organization,
        string $deducteeType,
        int $deducteeId,
        int $quarter,
        int $year
    ): TdsCertificate {
        $existing = TdsCertificate::where('organization_id', $organization->id)
            ->where('deductee_type', $deducteeType)
            ->where('deductee_id', $deducteeId)
            ->where('period_quarter', $quarter)
            ->where('period_year', $year)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $deductions = TdsDeduction::where('organization_id', $organization->id)
            ->where('deductee_type', $deducteeType)
            ->where('deductee_id', $deducteeId)
            ->forQuarter($quarter, $year)
            ->get();

        if ($deductions->isEmpty()) {
            throw new \RuntimeException('No TDS deductions found for the specified deductee and period.');
        }

        $totalAmount = $deductions->sum('payment_amount');
        $totalTds    = $deductions->sum('net_tds');

        return TdsCertificate::create([
            'organization_id'    => $organization->id,
            'deductee_type'      => $deducteeType,
            'deductee_id'        => $deducteeId,
            'period_quarter'     => $quarter,
            'period_year'        => $year,
            'certificate_number' => $this->generateCertificateNumber($organization->id, $quarter, $year),
            'total_amount'       => $totalAmount,
            'total_tds'          => $totalTds,
            'generated_at'       => now(),
        ]);
    }

    /**
     * Prepare a quarterly TDS return (Form 26Q).
     */
    public function prepareQuarterlyReturn(
        Organization $organization,
        int $quarter,
        int $year
    ): TdsReturn {
        $existing = TdsReturn::where('organization_id', $organization->id)
            ->forQuarter($quarter, $year)
            ->first();

        if ($existing !== null && $existing->isFiled()) {
            throw new \RuntimeException('TDS return for this quarter has already been filed.');
        }

        if ($existing !== null) {
            return $this->refreshReturnTotals($existing);
        }

        return DB::transaction(function () use ($organization, $quarter, $year): TdsReturn {
            $deductions = TdsDeduction::where('organization_id', $organization->id)
                ->forQuarter($quarter, $year)
                ->get();

            $totalDeductees    = $deductions->pluck('deductee_id')->unique()->count();
            $totalTransactions = $deductions->count();
            $totalAmount       = $deductions->sum('payment_amount');
            $totalTds          = $deductions->sum('net_tds');

            return TdsReturn::create([
                'organization_id'        => $organization->id,
                'quarter'                => $quarter,
                'financial_year'         => $year,
                'total_deductees'        => $totalDeductees,
                'total_transactions'     => $totalTransactions,
                'total_amount'           => $totalAmount,
                'total_tds'              => $totalTds,
                'status'                 => 'draft',
            ]);
        });
    }

    /**
     * File a quarterly TDS return.
     */
    public function fileReturn(TdsReturn $return, string $acknowledgementNumber): TdsReturn
    {
        if ($return->isFiled()) {
            throw new \RuntimeException('TDS return is already filed.');
        }

        $return->update([
            'status'                  => 'filed',
            'filed_at'                => now(),
            'acknowledgement_number'  => $acknowledgementNumber,
        ]);

        return $return->fresh();
    }

    /**
     * Get financial quarter for a date.
     * India financial year: Apr-Jun (Q1), Jul-Sep (Q2), Oct-Dec (Q3), Jan-Mar (Q4)
     */
    public function getQuarterForDate(string $date): int
    {
        $month = (int) date('n', strtotime($date));

        return match (true) {
            $month >= 4 && $month <= 6   => 1,
            $month >= 7 && $month <= 9   => 2,
            $month >= 10 && $month <= 12 => 3,
            default                       => 4, // Jan-Mar
        };
    }

    /**
     * Get financial year start year for a date.
     * Indian FY: April to March (FY 2025-26 starts April 2025, return as 2025).
     */
    public function getFinancialYearForDate(string $date): int
    {
        $month = (int) date('n', strtotime($date));
        $year  = (int) date('Y', strtotime($date));

        return $month >= 4 ? $year : $year - 1;
    }

    /**
     * Refresh totals on an existing draft TDS return.
     */
    private function refreshReturnTotals(TdsReturn $return): TdsReturn
    {
        $deductions = TdsDeduction::where('organization_id', $return->organization_id)
            ->forQuarter($return->quarter, $return->financial_year)
            ->get();

        $return->update([
            'total_deductees'    => $deductions->pluck('deductee_id')->unique()->count(),
            'total_transactions' => $deductions->count(),
            'total_amount'       => $deductions->sum('payment_amount'),
            'total_tds'          => $deductions->sum('net_tds'),
        ]);

        return $return->fresh();
    }

    /**
     * Generate a unique certificate number using a sequential sequence to
     * avoid the collision risk of the former Str::random approach.
     */
    private function generateCertificateNumber(int $organizationId, int $quarter, int $year): string
    {
        $seq = app(NumberGeneratorService::class)->generate('TDS_CERT', null, $organizationId);
        return "TDS-{$organizationId}-{$year}Q{$quarter}-{$seq}";
    }
}
