<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\TaxCategory;
use App\Models\Tax\TaxRate;

/**
 * ERP-integrated tax calculation service.
 *
 * Handles DB-driven tax rate lookups (TaxCategory, TaxRate models) and delegates
 * the actual arithmetic to TaxService, which is the authoritative math library.
 */
class TaxCalculatorService
{
    public function __construct(
        private readonly TaxService $taxService
    ) {}
    /**
     * Calculate taxes for document lines.
     */
    public function calculate(
        Organization $organization,
        array $lines,
        ?string $placeOfSupply = null
    ): TaxResult {
        if (empty($lines)) {
            throw new \InvalidArgumentException('Lines array cannot be empty.');
        }

        return match ($organization->tax_scheme) {
            'VAT' => $this->calculateVat($organization, $lines),
            'GST' => $this->calculateGst($organization, $lines, $placeOfSupply),
            default => $this->noTax($lines),
        };
    }

    /**
     * Calculate VAT for GCC countries.
     */
    protected function calculateVat(Organization $organization, array $lines): TaxResult
    {
        $result = new TaxResult();
        $countryCode = $organization->country_code;

        foreach ($lines as $index => $line) {
            $taxableAmount = $this->getLineSubtotal($line);
            $taxCategory = $this->getTaxCategory($line);
            $taxRate = $this->getVatRate($countryCode, $taxCategory);

            // Validate explicit tax_rate if provided
            if (isset($line['tax_rate'])) {
                $explicitRate = (float) $line['tax_rate'];
                if ($explicitRate < 0 || $explicitRate > 100) {
                    throw new \InvalidArgumentException("Tax rate must be between 0 and 100, got: {$explicitRate}");
                }
            }

            // Fall back to explicit tax_rate from line data if no rate found from tax categories
            if ($taxRate <= 0 && isset($line['tax_rate']) && $line['tax_rate'] > 0) {
                $taxRate = (float) $line['tax_rate'];
            }

            $taxAmount = '0';
            if ($taxRate > 0) {
                $calc = $this->taxService->calculateTaxOnExclusive((string) $taxableAmount, (string) $taxRate);
                $taxAmount = $calc->taxAmount;
            }

            $result->lines[$index] = [
                'taxable_amount' => $taxableAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => (float) $taxAmount,
                'tax_code' => $taxCategory?->code ?? 'S',
            ];

            $result->totalTaxableAmount = bcadd((string) $result->totalTaxableAmount, (string) $taxableAmount, 4);
            $result->totalTaxAmount = bcadd((string) $result->totalTaxAmount, $taxAmount, 4);
        }

        // Group by tax rate for summary
        $result->taxSummary = $this->groupByTaxRate($result->lines);

        return $result;
    }

    /**
     * Calculate GST for India.
     */
    protected function calculateGst(
        Organization $organization,
        array $lines,
        ?string $placeOfSupply
    ): TaxResult {
        $result = new TaxResult();
        $result->isGst = true;

        // Determine if inter-state or intra-state
        $sellerState = $organization->state_code ?? substr($organization->tax_number ?? '', 0, 2);
        $buyerState = $placeOfSupply;
        $isInterState = $sellerState !== $buyerState && $buyerState !== null;

        $result->isInterState = $isInterState;
        $result->placeOfSupply = $placeOfSupply;

        foreach ($lines as $index => $line) {
            $taxableAmount = $this->getLineSubtotal($line);
            $gstRate = $this->getGstRate($line);

            if ($isInterState) {
                // IGST = full rate
                $igstCalc = $this->taxService->calculateTaxOnExclusive((string) $taxableAmount, (string) $gstRate);
                $igstAmount = $igstCalc->taxAmount;

                $result->lines[$index] = [
                    'taxable_amount' => $taxableAmount,
                    'igst_rate' => $gstRate,
                    'igst_amount' => (float) $igstAmount,
                    'cgst_rate' => 0,
                    'cgst_amount' => 0,
                    'sgst_rate' => 0,
                    'sgst_amount' => 0,
                    'tax_amount' => (float) $igstAmount,
                    'hsn_code' => $line['hsn_code'] ?? null,
                ];

                $result->totalIgst = bcadd((string) $result->totalIgst, $igstAmount, 4);
            } else {
                // CGST + SGST = half each
                $halfRate = bcdiv((string) $gstRate, '2', 4);
                $cgstCalc = $this->taxService->calculateTaxOnExclusive((string) $taxableAmount, $halfRate);
                $cgstAmount = $cgstCalc->taxAmount;
                $sgstAmount = $cgstAmount; // Same as CGST

                $result->lines[$index] = [
                    'taxable_amount' => $taxableAmount,
                    'igst_rate' => 0,
                    'igst_amount' => 0,
                    'cgst_rate' => (float) $halfRate,
                    'cgst_amount' => (float) $cgstAmount,
                    'sgst_rate' => (float) $halfRate,
                    'sgst_amount' => (float) $sgstAmount,
                    'tax_amount' => (float) bcadd($cgstAmount, $sgstAmount, 4),
                    'hsn_code' => $line['hsn_code'] ?? null,
                ];

                $result->totalCgst = bcadd((string) $result->totalCgst, $cgstAmount, 4);
                $result->totalSgst = bcadd((string) $result->totalSgst, $sgstAmount, 4);
            }

            $result->totalTaxableAmount = bcadd((string) $result->totalTaxableAmount, (string) $taxableAmount, 4);
            $result->totalTaxAmount = bcadd(
                (string) $result->totalTaxAmount,
                (string) $result->lines[$index]['tax_amount'],
                4
            );
        }

        // Group by rate for GST summary
        $result->taxSummary = $this->groupByGstRate($result->lines, $isInterState);

        return $result;
    }

    /**
     * No tax calculation.
     */
    protected function noTax(array $lines): TaxResult
    {
        $result = new TaxResult();

        foreach ($lines as $index => $line) {
            $taxableAmount = $this->getLineSubtotal($line);

            $result->lines[$index] = [
                'taxable_amount' => $taxableAmount,
                'tax_rate' => 0,
                'tax_amount' => 0,
            ];

            $result->totalTaxableAmount = bcadd((string) $result->totalTaxableAmount, (string) $taxableAmount, 4);
        }

        return $result;
    }

    /**
     * Get line subtotal (before tax).
     */
    protected function getLineSubtotal(array $line): float
    {
        $quantity = $line['quantity'] ?? 1;
        if ((float)$quantity <= 0) {
            throw new \InvalidArgumentException('Line quantity must be positive.');
        }
        $unitPrice = $line['unit_price'] ?? 0;
        $discountAmount = $line['discount_amount'] ?? 0;

        $gross = bcmul((string) $quantity, (string) $unitPrice, 4);

        if (bccomp((string) $discountAmount, (string) $gross, 4) > 0) {
            throw new \InvalidArgumentException('Discount amount cannot exceed line gross amount.');
        }

        return (float) bcsub($gross, (string) $discountAmount, 4);
    }

    /**
     * Get tax category from line.
     */
    protected function getTaxCategory(array $line): ?TaxCategory
    {
        if (isset($line['tax_category_id'])) {
            return TaxCategory::find($line['tax_category_id']);
        }

        return TaxCategory::where('code', $line['tax_code'] ?? 'S')->first();
    }

    /**
     * Get VAT rate for a country.
     */
    protected function getVatRate(string $countryCode, ?TaxCategory $taxCategory): float
    {
        if (!$taxCategory || !$taxCategory->isTaxable()) {
            return 0;
        }

        $rate = TaxRate::where('tax_category_id', $taxCategory->id)
            ->forCountry($countryCode)
            ->effectiveOn(now())
            ->active()
            ->first();

        return (float) ($rate?->rate ?? 0);
    }

    /**
     * Get GST rate from line.
     */
    protected function getGstRate(array $line): float
    {
        // Explicit rate in line
        if (isset($line['gst_rate'])) {
            return (float) $line['gst_rate'];
        }

        // If CGST + SGST rates are provided, combine them
        if (isset($line['cgst_rate']) && isset($line['sgst_rate'])) {
            return (float) bcadd((string) $line['cgst_rate'], (string) $line['sgst_rate'], 4);
        }

        // If IGST rate is provided
        if (isset($line['igst_rate'])) {
            return (float) $line['igst_rate'];
        }

        // Get from HSN code
        if (isset($line['hsn_code'])) {
            try {
                $hsn = \App\Models\Tax\HsnSacCode::where('code', $line['hsn_code'])->first();
                if ($hsn) {
                    return (float) $hsn->gst_rate;
                }
            } catch (\Exception $e) {
                // Table might not exist
            }
        }

        // Use tax_rate if provided as fallback
        if (isset($line['tax_rate'])) {
            return (float) $line['tax_rate'];
        }

        // Derive rate from product's tax category
        if (isset($line['product_id'])) {
            try {
                $orgId = auth()->user()?->organization_id;
                $product = \App\Models\Inventory\Product::with('taxCategory')
                    ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                    ->find($line['product_id']);
                if ($product && $product->taxCategory && isset($product->taxCategory->gst_rate)) {
                    return (float) $product->taxCategory->gst_rate;
                }
            } catch (\Exception $e) {
                // Continue to final default
            }
        }

        // No rate found — throw so callers are forced to configure a rate.
        // Zero-rated items should supply gst_rate = 0 explicitly in the line data.
        throw new \RuntimeException(
            "No GST rate found for line (product_id: " . ($line['product_id'] ?? 'N/A') . "). "
            . "Configure a GST rate or supply gst_rate = 0 explicitly for zero-rated items before creating invoices."
        );
    }

    /**
     * Group lines by tax rate for summary.
     */
    protected function groupByTaxRate(array $lines): array
    {
        $summary = [];

        foreach ($lines as $line) {
            $rate = $line['tax_rate'];
            $key = (string) $rate;

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $rate,
                    'taxable_amount' => 0,
                    'tax_amount' => 0,
                ];
            }

            $summary[$key]['taxable_amount'] = bcadd(
                (string) $summary[$key]['taxable_amount'],
                (string) $line['taxable_amount'],
                4
            );
            $summary[$key]['tax_amount'] = bcadd(
                (string) $summary[$key]['tax_amount'],
                (string) $line['tax_amount'],
                4
            );
        }

        return array_values($summary);
    }

    /**
     * Group lines by GST rate for summary.
     */
    protected function groupByGstRate(array $lines, bool $isInterState): array
    {
        $summary = [];

        foreach ($lines as $line) {
            $rate = $isInterState ? $line['igst_rate'] : (float) bcadd((string) ($line['cgst_rate'] ?? '0'), (string) ($line['sgst_rate'] ?? '0'), 4);
            $key = (string) $rate;

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'rate' => $rate,
                    'taxable_amount' => 0,
                    'igst_amount' => 0,
                    'cgst_amount' => 0,
                    'sgst_amount' => 0,
                    'total_tax' => 0,
                ];
            }

            $summary[$key]['taxable_amount'] = bcadd(
                (string) $summary[$key]['taxable_amount'],
                (string) $line['taxable_amount'],
                4
            );

            if ($isInterState) {
                $summary[$key]['igst_amount'] = bcadd(
                    (string) $summary[$key]['igst_amount'],
                    (string) $line['igst_amount'],
                    4
                );
            } else {
                $summary[$key]['cgst_amount'] = bcadd(
                    (string) $summary[$key]['cgst_amount'],
                    (string) $line['cgst_amount'],
                    4
                );
                $summary[$key]['sgst_amount'] = bcadd(
                    (string) $summary[$key]['sgst_amount'],
                    (string) $line['sgst_amount'],
                    4
                );
            }

            $summary[$key]['total_tax'] = bcadd(
                (string) $summary[$key]['total_tax'],
                (string) $line['tax_amount'],
                4
            );
        }

        return array_values($summary);
    }

    /**
     * Calculate tax-inclusive to tax-exclusive conversion.
     */
    public function extractTaxFromInclusive(float $inclusiveAmount, float $taxRate): array
    {
        $calc = $this->taxService->extractTaxFromInclusive((string) $inclusiveAmount, (string) $taxRate);

        return [
            'base_amount' => (float) $calc->taxableAmount,
            'tax_amount' => (float) $calc->taxAmount,
            'tax_rate' => $taxRate,
        ];
    }
}

/**
 * Tax calculation result.
 */
class TaxResult
{
    public array $lines = [];
    public array $taxSummary = [];
    public string|float $totalTaxableAmount = 0;
    public string|float $totalTaxAmount = 0;

    // GST specific
    public bool $isGst = false;
    public bool $isInterState = false;
    public ?string $placeOfSupply = null;
    public string|float $totalCgst = 0;
    public string|float $totalSgst = 0;
    public string|float $totalIgst = 0;

    public function toArray(): array
    {
        $data = [
            'lines' => $this->lines,
            'summary' => $this->taxSummary,
            'totals' => [
                'taxable_amount' => $this->totalTaxableAmount,
                'tax_amount' => $this->totalTaxAmount,
            ],
        ];

        if ($this->isGst) {
            $data['gst'] = [
                'is_inter_state' => $this->isInterState,
                'place_of_supply' => $this->placeOfSupply,
                'cgst' => $this->totalCgst,
                'sgst' => $this->totalSgst,
                'igst' => $this->totalIgst,
            ];
        }

        return $data;
    }
}
