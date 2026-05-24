<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\UaeCitAssessment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UAE Corporate Income Tax (CIT) Service.
 *
 * Federal Decree-Law No. 47 of 2022:
 *   - 0%  on taxable income ≤ AED 375,000
 *   - 9%  on taxable income >  AED 375,000 (applied to the excess only)
 *   - Small Business Relief (SBR): entities with revenue ≤ AED 3,000,000 may elect
 *     to be treated as having 0% taxable income for tax periods ending on or before
 *     31 December 2026.
 *
 * Taxable income = accounting_income + add_backs − deductions
 *
 * CIT due = 9% × max(0, taxable_income − 375,000)
 *         = 0 if small_business_relief is elected
 *
 * Filing due date: 9 months after fiscal year-end (standard: Sep 30 of following year
 * for December year-ends).
 */
class UaeCorporateTaxService
{
    public const CIT_RATE_PCT         = 9.0;
    public const ZERO_RATE_THRESHOLD  = 375_000.0;
    public const SMALL_BIZ_THRESHOLD  = 3_000_000.0;

    // -------------------------------------------------------------------------
    // Calculation
    // -------------------------------------------------------------------------

    /**
     * Compute CIT liability components.
     *
     * @return array{
     *   accounting_income: float,
     *   add_backs: float,
     *   deductions: float,
     *   taxable_income: float,
     *   zero_rate_threshold: float,
     *   small_business_relief: bool,
     *   cit_rate: float,
     *   cit_due: float,
     * }
     */
    public function calculate(
        float $accountingIncome,
        float $addBacks = 0.0,
        float $deductions = 0.0,
        bool $smallBusinessRelief = false,
    ): array {
        $taxableIncome = (float) bcadd(
            bcsub((string) $accountingIncome, (string) $deductions, 4),
            (string) $addBacks,
            4
        );
        $taxableIncome = max(0.0, $taxableIncome);

        if ($smallBusinessRelief) {
            $citDue = 0.0;
        } else {
            $taxableAboveThreshold = max(0.0, $taxableIncome - self::ZERO_RATE_THRESHOLD);
            $citDue = round($taxableAboveThreshold * (self::CIT_RATE_PCT / 100), 4);
        }

        return [
            'accounting_income'    => $accountingIncome,
            'add_backs'            => $addBacks,
            'deductions'           => $deductions,
            'taxable_income'       => $taxableIncome,
            'zero_rate_threshold'  => self::ZERO_RATE_THRESHOLD,
            'small_business_relief' => $smallBusinessRelief,
            'cit_rate'             => self::CIT_RATE_PCT,
            'cit_due'              => $citDue,
        ];
    }

    // -------------------------------------------------------------------------
    // Persist assessment
    // -------------------------------------------------------------------------

    /**
     * Create (or re-draft) a CIT assessment for the given tax year.
     *
     * @param  array  $data  Must include: organization_id, tax_year, accounting_income.
     *                       Optional: add_backs, deductions, small_business_relief, fiscal_year_id, notes.
     * @throws InvalidArgumentException if a submitted/assessed/paid record already exists.
     */
    public function createAssessment(array $data, int $userId): UaeCitAssessment
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId  = (int) $data['organization_id'];
            $year   = (int) $data['tax_year'];

            $existing = UaeCitAssessment::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('tax_year', $year)
                ->first();

            if ($existing && !$existing->isDraft()) {
                throw new InvalidArgumentException(
                    "A UAE CIT assessment for {$year} already exists with status '{$existing->status}'."
                );
            }

            $calc = $this->calculate(
                (float) ($data['accounting_income'] ?? 0.0),
                (float) ($data['add_backs']          ?? 0.0),
                (float) ($data['deductions']         ?? 0.0),
                (bool)  ($data['small_business_relief'] ?? false),
            );

            // Filing due: 9 months after fiscal year end — default Sep 30 of year+1
            $filingDue = date('Y-m-d', mktime(0, 0, 0, 9, 30, $year + 1));

            $attributes = [
                'organization_id'         => $orgId,
                'fiscal_year_id'          => $data['fiscal_year_id'] ?? null,
                'tax_year'                => $year,
                'accounting_income'       => $calc['accounting_income'],
                'add_backs'               => $calc['add_backs'],
                'deductions'              => $calc['deductions'],
                'taxable_income'          => $calc['taxable_income'],
                'zero_rate_threshold'     => $calc['zero_rate_threshold'],
                'small_business_threshold' => self::SMALL_BIZ_THRESHOLD,
                'cit_rate'                => $calc['cit_rate'],
                'small_business_relief'   => $calc['small_business_relief'],
                'cit_due'                 => $calc['cit_due'],
                'cit_paid'                => $existing?->cit_paid ?? 0,
                'cit_remaining'           => $calc['cit_due'],
                'status'                  => UaeCitAssessment::STATUS_DRAFT,
                'filing_due_date'         => $filingDue,
                'notes'                   => $data['notes'] ?? null,
                'prepared_by'             => $userId,
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            return UaeCitAssessment::create($attributes);
        });
    }

    /**
     * Submit a draft CIT assessment to EmaraTax.
     */
    public function submitAssessment(UaeCitAssessment $assessment, ?string $emaraTaxRef = null): UaeCitAssessment
    {
        if (!$assessment->isDraft()) {
            throw new InvalidArgumentException('Only draft CIT assessments can be submitted.');
        }

        $assessment->update([
            'status'               => UaeCitAssessment::STATUS_SUBMITTED,
            'emara_tax_reference'  => $emaraTaxRef,
            'filed_at'             => now()->toDateString(),
        ]);

        return $assessment->fresh();
    }

    /**
     * Record a CIT payment.
     *
     * @throws InvalidArgumentException if payment exceeds outstanding balance.
     */
    public function recordPayment(UaeCitAssessment $assessment, float $amount): UaeCitAssessment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        $outstanding = $assessment->outstandingBalance();

        if ($amount > $outstanding + 0.0001) {
            throw new InvalidArgumentException(
                "Payment ({$amount}) exceeds outstanding CIT balance ({$outstanding})."
            );
        }

        $newPaid      = (float) bcadd((string) $assessment->cit_paid, (string) $amount, 4);
        $newRemaining = max(0.0, (float) bcsub((string) $assessment->cit_due, (string) $newPaid, 4));

        $assessment->update([
            'cit_paid'      => $newPaid,
            'cit_remaining' => $newRemaining,
            'status'        => $newRemaining <= 0.001
                ? UaeCitAssessment::STATUS_PAID
                : $assessment->status,
        ]);

        return $assessment->fresh();
    }
}
