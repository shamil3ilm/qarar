<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\ZakatAssessment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Zakat Calculation Service (SAP GAZT / ZATCA-03 equivalent).
 *
 * Computes the annual Zakat liability for Saudi organizations under the
 * General Authority of Zakat and Tax (GAZT) rules.
 *
 * Zakat formula:
 *   Zakat base = Total assets − Total liabilities − Non-Zakatable assets
 *   Zakat due  = Zakat base × 2.5% × (Saudi ownership %)
 *
 * Non-Zakatable assets include:
 *   - Fixed assets (property, plant & equipment)
 *   - Long-term investments in subsidiaries
 *   - Intangible assets
 *
 * Only applies to organizations in Saudi Arabia (country_code = 'SA').
 */
class ZakatCalculationService
{
    public const ZAKAT_RATE_PCT = 2.5;

    // Account sub-types treated as non-Zakatable (fixed / long-term assets)
    private const NON_ZAKATABLE_SUBTYPES = [
        'fixed_asset',
        'accumulated_depreciation',
        'intangible_asset',
        'long_term_investment',
    ];

    // -------------------------------------------------------------------------
    // Base calculation (from Chart of Accounts balances)
    // -------------------------------------------------------------------------

    /**
     * Calculate the Zakat base from the organization's posted journal balances.
     *
     * Callers may pass explicit balance overrides (useful in tests or when the
     * base figures come from an external trial balance export). When not supplied,
     * the method computes them by summing `base_debit − base_credit` from all
     * posted journal entry lines for the organization's accounts.
     *
     * Returns an array with all components and the final Zakat due amount.
     * Does NOT persist anything — use createAssessment() to persist.
     *
     * @param  float|null  $totalAssets          Override computed total assets
     * @param  float|null  $totalLiabilities     Override computed total liabilities
     * @param  float|null  $nonZakatableAssets   Override computed non-Zakatable assets
     *
     * @return array{
     *   total_assets: float,
     *   total_liabilities: float,
     *   non_zakatable_assets: float,
     *   zakat_base: float,
     *   saudi_ownership_pct: float,
     *   zakat_rate: float,
     *   zakat_due: float,
     * }
     */
    public function calculateBase(
        int $organizationId,
        float $saudiOwnershipPct = 100.0,
        ?float $totalAssets = null,
        ?float $totalLiabilities = null,
        ?float $nonZakatableAssets = null,
    ): array {
        if ($saudiOwnershipPct < 0 || $saudiOwnershipPct > 100) {
            throw new InvalidArgumentException('Saudi ownership percentage must be between 0 and 100.');
        }

        $totalAssets      = $totalAssets      ?? $this->sumAccountTypeFromJournals($organizationId, 'asset');
        $totalLiabilities = $totalLiabilities ?? $this->sumAccountTypeFromJournals($organizationId, 'liability');
        $nonZakatable     = $nonZakatableAssets ?? $this->sumNonZakatableAssetsFromJournals($organizationId);

        // Zakat base must be non-negative
        $zakatBase = max(0.0, (float) bcsub(
            bcsub((string) $totalAssets, (string) $totalLiabilities, 4),
            (string) $nonZakatable,
            4
        ));
        $nonZakatable = $nonZakatable; // bind to descriptive name for return

        // Apply Saudi ownership fraction
        $zakatDue = round(
            $zakatBase * (self::ZAKAT_RATE_PCT / 100) * ($saudiOwnershipPct / 100),
            4
        );

        return [
            'total_assets'         => $totalAssets,
            'total_liabilities'    => $totalLiabilities,
            'non_zakatable_assets' => $nonZakatable,
            'zakat_base'           => $zakatBase,
            'saudi_ownership_pct'  => $saudiOwnershipPct,
            'zakat_rate'           => self::ZAKAT_RATE_PCT,
            'zakat_due'            => $zakatDue,
        ];
    }

    // -------------------------------------------------------------------------
    // Persist assessment
    // -------------------------------------------------------------------------

    /**
     * Create (or update an existing draft) Zakat assessment for a given year.
     *
     * @param  array  $data  Must include: organization_id, assessment_year.
     *                       Optional: fiscal_year_id, saudi_ownership_pct, hijri_year, notes.
     * @throws InvalidArgumentException if an assessed/submitted record already exists for the year.
     */
    public function createAssessment(array $data, int $userId): ZakatAssessment
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId  = (int) $data['organization_id'];
            $year   = (int) $data['assessment_year'];
            $ownerPct = (float) ($data['saudi_ownership_pct'] ?? 100.0);

            // Guard: do not overwrite a submitted/assessed/paid record
            $existing = ZakatAssessment::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('assessment_year', $year)
                ->first();

            if ($existing && !$existing->isDraft()) {
                throw new InvalidArgumentException(
                    "A Zakat assessment for {$year} already exists with status '{$existing->status}'."
                );
            }

            $calc = $this->calculateBase(
                $orgId,
                $ownerPct,
                isset($data['total_assets'])        ? (float) $data['total_assets']        : null,
                isset($data['total_liabilities'])   ? (float) $data['total_liabilities']   : null,
                isset($data['non_zakatable_assets']) ? (float) $data['non_zakatable_assets'] : null,
            );

            // Filing due date: typically 120 days after fiscal year end (ZATCA rule)
            $filingDue = date('Y-m-d', mktime(0, 0, 0, 4, 30, $year + 1)); // Apr 30 following year

            $attributes = [
                'organization_id'      => $orgId,
                'fiscal_year_id'       => $data['fiscal_year_id'] ?? null,
                'assessment_year'      => $year,
                'hijri_year'           => $data['hijri_year'] ?? null,
                'total_assets'         => $calc['total_assets'],
                'total_liabilities'    => $calc['total_liabilities'],
                'non_zakatable_assets' => $calc['non_zakatable_assets'],
                'zakat_base'           => $calc['zakat_base'],
                'zakat_rate'           => $calc['zakat_rate'],
                'zakat_due'            => $calc['zakat_due'],
                'saudi_ownership_pct'  => $ownerPct,
                'zakat_paid'           => $existing?->zakat_paid ?? 0,
                'zakat_remaining'      => $calc['zakat_due'],
                'status'               => ZakatAssessment::STATUS_DRAFT,
                'filing_due_date'      => $filingDue,
                'notes'                => $data['notes'] ?? null,
                'prepared_by'          => $userId,
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            return ZakatAssessment::create($attributes);
        });
    }

    /**
     * Submit a draft assessment to GAZT / ZATCA.
     * Transitions status: draft → submitted.
     */
    public function submitAssessment(ZakatAssessment $assessment, ?string $gaztReference = null): ZakatAssessment
    {
        if (!$assessment->isDraft()) {
            throw new InvalidArgumentException('Only draft assessments can be submitted.');
        }

        $assessment->update([
            'status'          => ZakatAssessment::STATUS_SUBMITTED,
            'gazt_reference'  => $gaztReference,
            'filed_at'        => now()->toDateString(),
        ]);

        return $assessment->fresh();
    }

    /**
     * Record a Zakat payment and update remaining balance.
     *
     * @throws InvalidArgumentException if payment exceeds outstanding balance.
     */
    public function recordPayment(ZakatAssessment $assessment, float $amount): ZakatAssessment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be positive.');
        }

        $outstanding = $assessment->outstandingBalance();

        if ($amount > $outstanding + 0.0001) {
            throw new InvalidArgumentException(
                "Payment ({$amount}) exceeds outstanding Zakat balance ({$outstanding})."
            );
        }

        $newPaid      = (float) bcadd((string) $assessment->zakat_paid, (string) $amount, 4);
        $newRemaining = max(0.0, (float) bcsub((string) $assessment->zakat_due, (string) $newPaid, 4));

        $assessment->update([
            'zakat_paid'      => $newPaid,
            'zakat_remaining' => $newRemaining,
            'status'          => $newRemaining <= 0.001
                ? ZakatAssessment::STATUS_PAID
                : $assessment->status,
        ]);

        return $assessment->fresh();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Sum net posted journal balance for a given account_type.
     * For assets/expenses (debit-normal): net = SUM(base_debit) - SUM(base_credit)
     * For liabilities/equity/income (credit-normal): net = SUM(base_credit) - SUM(base_debit)
     */
    private function sumAccountTypeFromJournals(int $organizationId, string $accountType): float
    {
        $isDebitNormal = in_array($accountType, ['asset', 'expense'], true);

        $rows = DB::table('journal_entry_lines as jel')
            ->join('chart_of_accounts as coa', 'jel.account_id', '=', 'coa.id')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('je.organization_id', $organizationId)
            ->where('coa.organization_id', $organizationId)
            ->where('je.status', 'posted')
            ->where('coa.account_type', $accountType)
            ->whereNull('je.voided_at')
            ->selectRaw('SUM(jel.base_debit) as total_debit, SUM(jel.base_credit) as total_credit')
            ->first();

        $debit  = (float) ($rows->total_debit  ?? 0);
        $credit = (float) ($rows->total_credit ?? 0);

        return abs($isDebitNormal ? $debit - $credit : $credit - $debit);
    }

    private function sumNonZakatableAssetsFromJournals(int $organizationId): float
    {
        $rows = DB::table('journal_entry_lines as jel')
            ->join('chart_of_accounts as coa', 'jel.account_id', '=', 'coa.id')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('je.organization_id', $organizationId)
            ->where('coa.organization_id', $organizationId)
            ->where('je.status', 'posted')
            ->where('coa.account_type', 'asset')
            ->whereIn('coa.sub_type', self::NON_ZAKATABLE_SUBTYPES)
            ->whereNull('je.voided_at')
            ->selectRaw('SUM(jel.base_debit) as total_debit, SUM(jel.base_credit) as total_credit')
            ->first();

        $debit  = (float) ($rows->total_debit  ?? 0);
        $credit = (float) ($rows->total_credit ?? 0);

        return abs($debit - $credit);
    }
}
