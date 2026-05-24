<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\TdsDeduction;
use App\Models\Tax\TdsSection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TDS Section 194Q — TDS on Purchase of Goods.
 *
 * Finance Act 2021 (effective 1 July 2021):
 *
 *   WHEN applicable:
 *     - Buyer's turnover > ₹10 crore in preceding FY
 *     - Purchase value from a single resident seller > ₹50 lakh in the FY
 *     - Seller is NOT covered under TCS Section 206C(1H)
 *
 *   RATE:
 *     - 0.1% of purchase amount exceeding ₹50 lakh threshold (PAN available)
 *     - 5.0% if PAN not available
 *
 *   FINANCIAL YEAR: April 1 – March 31 (India)
 *
 *   TDS is deducted at time of credit to seller's account or payment,
 *   whichever is earlier.
 */
class Tds194QService
{
    /** Annual purchase threshold per seller (₹50 lakh) */
    public const THRESHOLD_PER_FY = 5_000_000.0;

    /** Standard TDS rate (with PAN) */
    public const RATE_WITH_PAN = 0.10; // 0.1%

    /** TDS rate when PAN not available */
    public const RATE_NO_PAN = 5.00; // 5%

    // -------------------------------------------------------------------------

    /**
     * Determine the India Financial Year boundaries for a given date.
     *
     * @return array{start: string, end: string, label: string, quarter: int, year: int}
     */
    public function financialYear(string $date): array
    {
        $d     = new \DateTimeImmutable($date);
        $month = (int) $d->format('n');
        $year  = (int) $d->format('Y');

        $fyStart = $month >= 4 ? $year : $year - 1;
        $fyEnd   = $fyStart + 1;

        // Quarter within the FY (Q1=Apr-Jun, Q2=Jul-Sep, Q3=Oct-Dec, Q4=Jan-Mar)
        $normalizedMonth = $month >= 4 ? $month - 3 : $month + 9; // 1-12 within FY
        $quarter = (int) ceil($normalizedMonth / 3);

        return [
            'start'   => "{$fyStart}-04-01",
            'end'     => "{$fyEnd}-03-31",
            'label'   => "FY {$fyStart}-" . substr((string) $fyEnd, 2),
            'quarter' => $quarter,
            'year'    => $fyStart,  // FY year key (the starting year)
        ];
    }

    /**
     * Calculate 194Q TDS for a purchase payment.
     *
     * Returns zero if:
     *  - Cumulative purchases from this seller in the FY are still below ₹50 lakh
     *  - Seller is covered by 206C(1H)
     *
     * @param  int     $organizationId  Buyer organization
     * @param  int     $deducteeId      Seller / vendor ID
     * @param  float   $purchaseAmount  Amount of this purchase
     * @param  string  $paymentDate     Date of payment (Y-m-d)
     * @param  bool    $hasPan          Whether the seller has a PAN
     * @param  bool    $isCoveredBy206C Whether seller collects TCS under 206C(1H)
     *
     * @return array{
     *   purchase_amount: float,
     *   cumulative_ytd: float,
     *   threshold: float,
     *   taxable_amount: float,
     *   tds_rate: float,
     *   tds_amount: float,
     *   applies: bool,
     *   reason: string,
     * }
     */
    public function calculate(
        int $organizationId,
        int $deducteeId,
        float $purchaseAmount,
        string $paymentDate,
        bool $hasPan = true,
        bool $isCoveredBy206C = false,
    ): array {
        if ($isCoveredBy206C) {
            return $this->nilResult($purchaseAmount, 0.0, '206C(1H) applies — seller collects TCS instead');
        }

        $fy      = $this->financialYear($paymentDate);
        $section = TdsSection::where('section_code', '194Q')->where('is_active', true)->first();

        if (!$section) {
            return $this->nilResult($purchaseAmount, 0.0, 'Section 194Q not configured in TDS sections');
        }

        // Sum existing deductions from this deductee in current FY
        $cumulativeYtd = (float) TdsDeduction::where('organization_id', $organizationId)
            ->where('deductee_id', $deducteeId)
            ->where('section_id', $section->id)
            ->whereBetween('payment_date', [$fy['start'], $fy['end']])
            ->sum('payment_amount');

        $previousTotal = $cumulativeYtd;
        $newTotal      = $previousTotal + $purchaseAmount;

        if ($newTotal <= self::THRESHOLD_PER_FY) {
            return $this->nilResult($purchaseAmount, $previousTotal, 'Cumulative purchases below ₹50 lakh threshold');
        }

        // Only the amount that pushes the total over threshold is taxable
        $alreadyAboveThreshold = max(0.0, $previousTotal - self::THRESHOLD_PER_FY);
        $newlyTaxable = $purchaseAmount - max(0.0, self::THRESHOLD_PER_FY - $previousTotal) - $alreadyAboveThreshold;
        $taxableAmount = max(0.0, min($newlyTaxable, $purchaseAmount));

        $rate      = $hasPan ? self::RATE_WITH_PAN : self::RATE_NO_PAN;
        $tdsAmount = round($taxableAmount * ($rate / 100), 2);

        return [
            'purchase_amount' => $purchaseAmount,
            'cumulative_ytd'  => $previousTotal,
            'threshold'       => self::THRESHOLD_PER_FY,
            'taxable_amount'  => $taxableAmount,
            'tds_rate'        => $rate,
            'tds_amount'      => $tdsAmount,
            'applies'         => true,
            'reason'          => '194Q applicable — cumulative purchases exceed ₹50 lakh threshold',
        ];
    }

    /**
     * Record a 194Q TDS deduction for a purchase transaction.
     *
     * @param  array  $data  Must include: organization_id, deductee_id, payment_amount, payment_date.
     *                       Optional: has_pan, is_covered_by_206c, deductee_type, source_type, source_id.
     * @return TdsDeduction
     * @throws InvalidArgumentException if TDS does not apply or section not found.
     */
    public function recordDeduction(array $data): TdsDeduction
    {
        return DB::transaction(function () use ($data) {
            $section = TdsSection::where('section_code', '194Q')->where('is_active', true)->firstOrFail();

            $calc = $this->calculate(
                (int)  $data['organization_id'],
                (int)  $data['deductee_id'],
                (float) $data['payment_amount'],
                (string) $data['payment_date'],
                (bool) ($data['has_pan'] ?? true),
                (bool) ($data['is_covered_by_206c'] ?? false),
            );

            if (!$calc['applies'] || $calc['tds_amount'] <= 0) {
                throw new InvalidArgumentException("194Q TDS does not apply: {$calc['reason']}");
            }

            $fy = $this->financialYear($data['payment_date']);

            return TdsDeduction::create([
                'organization_id' => $data['organization_id'],
                'deductee_type'   => $data['deductee_type'] ?? 'vendor',
                'deductee_id'     => $data['deductee_id'],
                'section_id'      => $section->id,
                'payment_date'    => $data['payment_date'],
                'payment_amount'  => $data['payment_amount'],
                'tds_rate'        => $calc['tds_rate'],
                'tds_amount'      => $calc['tds_amount'],
                'surcharge'       => 0.0,
                'education_cess'  => 0.0,
                'net_tds'         => $calc['tds_amount'],
                'source_type'     => $data['source_type'] ?? 'purchase',
                'source_id'       => $data['source_id'] ?? null,
                'period_quarter'  => $fy['quarter'],
                'period_year'     => $fy['year'],
            ]);
        });
    }

    // -------------------------------------------------------------------------

    /** @return array{purchase_amount: float, cumulative_ytd: float, threshold: float, taxable_amount: float, tds_rate: float, tds_amount: float, applies: bool, reason: string} */
    private function nilResult(float $amount, float $ytd, string $reason): array
    {
        return [
            'purchase_amount' => $amount,
            'cumulative_ytd'  => $ytd,
            'threshold'       => self::THRESHOLD_PER_FY,
            'taxable_amount'  => 0.0,
            'tds_rate'        => 0.0,
            'tds_amount'      => 0.0,
            'applies'         => false,
            'reason'          => $reason,
        ];
    }
}
