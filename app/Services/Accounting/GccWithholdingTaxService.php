<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\WithholdingTaxCode;
use App\Models\Accounting\WithholdingTaxLine;
use App\Models\Core\Organization;
use Database\Seeders\GccCrossBorderWhtSeeder;

/**
 * GCC Cross-Border Withholding Tax helper (SAP OBWW / F.68 equivalent).
 *
 * Wraps the generic WithholdingTaxService to add GCC-specific logic:
 *   1. Seed/ensure GCC WHT codes exist for the organization.
 *   2. Resolve the correct WHT code based on vendor country vs org country.
 *   3. Apply WHT only when the payment is genuinely cross-border.
 *
 * Statutory rates (2026):
 *   SA — 15%  ZATCA technical services, royalties, management fees
 *   AE —  0%  no WHT regime
 *   OM — 10%  dividends, interest, royalties, management fees
 *   KW —  5%  limited circumstances
 *   BH —  0%  no WHT regime
 *   QA —  5%  dividends to non-residents
 */
class GccWithholdingTaxService
{
    private const GCC_COUNTRIES = ['SA', 'AE', 'OM', 'KW', 'BH', 'QA'];

    public function __construct(
        private readonly WithholdingTaxService $whtService,
    ) {}

    // -------------------------------------------------------------------------
    // Seeding / setup
    // -------------------------------------------------------------------------

    /**
     * Ensure GCC cross-border WHT codes are present for this organization.
     * Idempotent — safe to call on every deployment.
     */
    public function ensureCodesExist(int $organizationId): void
    {
        (new GccCrossBorderWhtSeeder())->createCodesForOrganization($organizationId);
    }

    // -------------------------------------------------------------------------
    // Code resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve the GCC cross-border WHT code for a given vendor country.
     *
     * Returns null when:
     *   - The vendor country is not a GCC country.
     *   - No code has been seeded (codes not yet initialized).
     */
    public function resolveCode(int $organizationId, string $vendorCountryCode): ?WithholdingTaxCode
    {
        $vendorCountry = strtoupper($vendorCountryCode);

        if (!in_array($vendorCountry, self::GCC_COUNTRIES, true)) {
            return null;
        }

        return WithholdingTaxCode::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('code', "GCC-XB-{$vendorCountry}")
            ->where('tax_type', 'cross_border_wht')
            ->where('is_active', true)
            ->first();
    }

    // -------------------------------------------------------------------------
    // Cross-border detection
    // -------------------------------------------------------------------------

    /**
     * Determine whether a payment is cross-border (i.e., WHT applies).
     *
     * A payment is cross-border when the vendor's country differs from the
     * paying organization's country.
     */
    public function isCrossBorder(int $organizationId, string $vendorCountryCode): bool
    {
        $org = Organization::withoutGlobalScopes()->find($organizationId);

        if ($org === null) {
            return false;
        }

        return strtoupper($org->country_code) !== strtoupper($vendorCountryCode);
    }

    // -------------------------------------------------------------------------
    // WHT application
    // -------------------------------------------------------------------------

    /**
     * Apply WHT to a cross-border vendor payment if applicable.
     *
     * Returns the WHT line when deducted, null when:
     *   - The payment is domestic (same country).
     *   - No matching GCC WHT code exists.
     *   - The applicable rate is 0% (AE, BH) — no line created; use zeroRateExemption().
     *
     * @param  array  $paymentContext  [payment_type, payment_id, contact_id, gross_amount,
     *                                  currency_code, transaction_date, organization_id, vendor_country_code]
     * @param  array  $journalMeta    forwarded to JournalService
     */
    public function applyIfCrossBorder(array $paymentContext, array $journalMeta): ?WithholdingTaxLine
    {
        $orgId         = (int) $paymentContext['organization_id'];
        $vendorCountry = strtoupper($paymentContext['vendor_country_code'] ?? '');

        if (!$this->isCrossBorder($orgId, $vendorCountry)) {
            return null; // Domestic payment — no cross-border WHT
        }

        $code = $this->resolveCode($orgId, $vendorCountry);

        if ($code === null) {
            return null; // Non-GCC vendor or code not seeded
        }

        // Zero-rate countries (AE, BH): skip deduction but still document
        if ((float) $code->rate === 0.0) {
            return null;
        }

        return $this->whtService->applyToPayment($code, $paymentContext, $journalMeta);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the statutory WHT rate for a given country (regardless of seeding state).
     * Returns null for non-GCC countries.
     */
    public function statutoryRate(string $countryCode): ?float
    {
        $rates = [
            'SA' => 15.0,
            'AE' =>  0.0,
            'OM' => 10.0,
            'KW' =>  5.0,
            'BH' =>  0.0,
            'QA' =>  5.0,
        ];

        return $rates[strtoupper($countryCode)] ?? null;
    }
}
