<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\TaxDeterminationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxDeterminationService
{
    /**
     * Find the highest-priority (lowest priority number) matching active rule
     * for the given context.
     *
     * Context keys:
     *   document_type   — one of TaxDeterminationRule::DOCUMENT_TYPE_*
     *   from_country    — ISO 2-char country code, nullable
     *   to_country      — ISO 2-char country code, nullable
     *   from_region     — state / emirate, nullable
     *   to_region       — state / emirate, nullable
     *   tax_category_id — int, nullable
     *   customer_type   — one of TaxDeterminationRule::CUSTOMER_TYPE_*, nullable
     */
    public function determineRule(array $context): ?TaxDeterminationRule
    {
        $docType      = $context['document_type'] ?? TaxDeterminationRule::DOCUMENT_TYPE_ALL;
        $fromCountry  = $context['from_country'] ?? null;
        $toCountry    = $context['to_country'] ?? null;
        $fromRegion   = $context['from_region'] ?? null;
        $toRegion     = $context['to_region'] ?? null;
        $taxCategoryId = $context['tax_category_id'] ?? null;
        $customerType = $context['customer_type'] ?? null;

        return TaxDeterminationRule::active()
            ->forDocument($docType)
            ->when($fromCountry !== null, function ($q) use ($fromCountry): void {
                $q->where(function ($q2) use ($fromCountry): void {
                    $q2->whereNull('from_country_code')
                       ->orWhere('from_country_code', $fromCountry);
                });
            })
            ->when($toCountry !== null, function ($q) use ($toCountry): void {
                $q->where(function ($q2) use ($toCountry): void {
                    $q2->whereNull('to_country_code')
                       ->orWhere('to_country_code', $toCountry);
                });
            })
            ->when($fromRegion !== null, function ($q) use ($fromRegion): void {
                $q->where(function ($q2) use ($fromRegion): void {
                    $q2->whereNull('from_region')
                       ->orWhere('from_region', $fromRegion);
                });
            })
            ->when($toRegion !== null, function ($q) use ($toRegion): void {
                $q->where(function ($q2) use ($toRegion): void {
                    $q2->whereNull('to_region')
                       ->orWhere('to_region', $toRegion);
                });
            })
            ->when($taxCategoryId !== null, function ($q) use ($taxCategoryId): void {
                $q->where(function ($q2) use ($taxCategoryId): void {
                    $q2->whereNull('tax_category_id')
                       ->orWhere('tax_category_id', $taxCategoryId);
                });
            })
            ->when($customerType !== null, function ($q) use ($customerType): void {
                $q->where(function ($q2) use ($customerType): void {
                    $q2->where('customer_type', TaxDeterminationRule::CUSTOMER_TYPE_ANY)
                       ->orWhere('customer_type', $customerType);
                });
            })
            ->orderBy('priority', 'asc')
            ->first();
    }

    /**
     * Determine the tax for a single line item.
     * Returns an array with keys: tax_type, tax_rate, is_reverse_charge, rule_id
     */
    public function determineForLine(array $lineContext): array
    {
        $rule = $this->determineRule($lineContext);

        if ($rule === null) {
            return [
                'tax_type'         => TaxDeterminationRule::TAX_TYPE_STANDARD,
                'tax_rate'         => 0.0,
                'is_reverse_charge' => false,
                'rule_id'          => null,
            ];
        }

        $rate = 0.0;
        if ($rule->taxRate !== null) {
            $rate = (float) $rule->taxRate->rate;
        }

        return [
            'tax_type'         => $rule->tax_type,
            'tax_rate'         => $rate,
            'is_reverse_charge' => (bool) $rule->is_reverse_charge,
            'rule_id'          => $rule->id,
        ];
    }

    /**
     * Calculate the tax for a given amount using the determination result.
     *
     * @param  float  $amount  Net (pre-tax) amount
     * @param  array  $determination  Output of determineForLine()
     */
    public function calculateTax(float $amount, array $determination): array
    {
        $taxRatePct    = (float) ($determination['tax_rate'] ?? 0);
        $taxType       = $determination['tax_type'] ?? TaxDeterminationRule::TAX_TYPE_STANDARD;
        $isReverseCharge = (bool) ($determination['is_reverse_charge'] ?? false);

        // Zero-rated, exempt, out-of-scope, or reverse-charge (buyer accounts for tax) → 0 tax on invoice
        $taxAmount = 0.0;
        if (
            $taxType === TaxDeterminationRule::TAX_TYPE_STANDARD
            && ! $isReverseCharge
            && $taxRatePct > 0
        ) {
            $taxAmount = (float) bcmul(
                (string) $amount,
                bcdiv((string) $taxRatePct, '100', 6),
                4
            );
        }

        return [
            'tax_amount'       => $taxAmount,
            'tax_rate_pct'     => $taxRatePct,
            'tax_type'         => $taxType,
            'is_reverse_charge' => $isReverseCharge,
            'net_amount'       => $amount,
            'gross_amount'     => (float) bcadd((string) $amount, (string) $taxAmount, 4),
        ];
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function list(array $filters): LengthAwarePaginator
    {
        $query = TaxDeterminationRule::with(['taxCategory', 'taxRate'])
            ->when(
                isset($filters['document_type']),
                fn ($q) => $q->forDocument($filters['document_type'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy('priority', 'asc');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data): TaxDeterminationRule
    {
        return TaxDeterminationRule::create($data);
    }

    public function update(TaxDeterminationRule $rule, array $data): TaxDeterminationRule
    {
        $rule->update($data);
        return $rule->fresh(['taxCategory', 'taxRate']);
    }

    public function delete(TaxDeterminationRule $rule): void
    {
        $rule->delete();
    }
}
