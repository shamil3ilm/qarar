<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\BahrainVatReturn;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Bahrain VAT Return Service (NBR — National Bureau for Revenue).
 *
 * VAT rate:  10% on standard-rated supplies (effective 1 January 2022)
 * Period:    Quarterly (or monthly for large taxpayers with annual taxable
 *            supplies ≥ BHD 3,000,000)
 * Filing:    Last day of the month following the period end
 *            (Q1: 30 Apr | Q2: 31 Jul | Q3: 31 Oct | Q4: 31 Jan)
 *
 * Return structure (NBR form boxes):
 *   Box 1  Standard-rated supplies (BHD)
 *   Box 2  Zero-rated supplies
 *   Box 3  Exempt supplies
 *   Box 4  Output VAT  = Box 1 × 10%
 *   Box 5  Standard-rated purchases (input VAT reclaimable)
 *   Box 6  Capital goods input tax
 *   Box 7  Total input VAT = Box 5 × 10% + Box 6
 *   Box 8  Net VAT payable = Box 4 − Box 7 (negative = refund)
 */
class BahrainVatReturnService
{
    public const VAT_RATE_PCT = 10.0;

    // Map of quarter → filing due date (month, day)
    private const QUARTERLY_DUE = [
        1 => [4, 30],   // Q1 (Jan–Mar) → Apr 30
        2 => [7, 31],   // Q2 (Apr–Jun) → Jul 31
        3 => [10, 31],  // Q3 (Jul–Sep) → Oct 31
        4 => [1, 31],   // Q4 (Oct–Dec) → Jan 31 of following year
    ];

    // -------------------------------------------------------------------------
    // Calculation
    // -------------------------------------------------------------------------

    /**
     * Compute VAT return figures from supply/purchase inputs.
     *
     * @return array{
     *   standard_rated_supplies: float,
     *   zero_rated_supplies: float,
     *   exempt_supplies: float,
     *   output_vat: float,
     *   standard_rated_purchases: float,
     *   capital_goods_input_tax: float,
     *   total_input_vat: float,
     *   net_vat_payable: float,
     *   vat_rate: float,
     * }
     */
    public function calculate(
        float $standardRatedSupplies,
        float $zeroRatedSupplies = 0.0,
        float $exemptSupplies = 0.0,
        float $standardRatedPurchases = 0.0,
        float $capitalGoodsInputTax = 0.0,
    ): array {
        $outputVat      = round($standardRatedSupplies * (self::VAT_RATE_PCT / 100), 4);
        $inputFromPurch = round($standardRatedPurchases * (self::VAT_RATE_PCT / 100), 4);
        $totalInputVat  = round($inputFromPurch + $capitalGoodsInputTax, 4);
        $netVatPayable  = round($outputVat - $totalInputVat, 4);

        return [
            'standard_rated_supplies'  => $standardRatedSupplies,
            'zero_rated_supplies'      => $zeroRatedSupplies,
            'exempt_supplies'          => $exemptSupplies,
            'output_vat'               => $outputVat,
            'standard_rated_purchases' => $standardRatedPurchases,
            'capital_goods_input_tax'  => $capitalGoodsInputTax,
            'total_input_vat'          => $totalInputVat,
            'net_vat_payable'          => $netVatPayable,
            'vat_rate'                 => self::VAT_RATE_PCT,
        ];
    }

    // -------------------------------------------------------------------------
    // Persist return
    // -------------------------------------------------------------------------

    /**
     * Create (or re-draft) a VAT return for the given period.
     *
     * @param  array  $data  Must include: organization_id, period_year, period_quarter (quarterly)
     *                       or period_month (monthly).
     *                       Optional: standard_rated_supplies, zero_rated_supplies, exempt_supplies,
     *                       standard_rated_purchases, capital_goods_input_tax, notes.
     * @throws InvalidArgumentException if a submitted/accepted/paid record already exists.
     */
    public function createReturn(array $data, int $userId): BahrainVatReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId   = (int) $data['organization_id'];
            $year    = (int) $data['period_year'];
            $quarter = isset($data['period_quarter']) ? (int) $data['period_quarter'] : null;
            $month   = isset($data['period_month'])   ? (int) $data['period_month']   : null;

            if ($quarter !== null && ($quarter < 1 || $quarter > 4)) {
                throw new InvalidArgumentException('period_quarter must be between 1 and 4.');
            }

            [$periodStart, $periodEnd] = $this->resolvePeriodDates($year, $quarter, $month);
            $filingDue = $this->resolveFilingDue($year, $quarter, $month);

            $existing = BahrainVatReturn::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('period_year', $year)
                ->where('period_quarter', $quarter)
                ->where('period_month', $month)
                ->first();

            if ($existing && !$existing->isDraft()) {
                throw new InvalidArgumentException(
                    "A Bahrain VAT return for this period already exists with status '{$existing->status}'."
                );
            }

            $calc = $this->calculate(
                (float) ($data['standard_rated_supplies']  ?? 0.0),
                (float) ($data['zero_rated_supplies']      ?? 0.0),
                (float) ($data['exempt_supplies']          ?? 0.0),
                (float) ($data['standard_rated_purchases'] ?? 0.0),
                (float) ($data['capital_goods_input_tax']  ?? 0.0),
            );

            $attributes = array_merge($calc, [
                'organization_id' => $orgId,
                'period_type'     => $quarter ? 'quarterly' : 'monthly',
                'period_quarter'  => $quarter,
                'period_month'    => $month,
                'period_year'     => $year,
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'status'          => BahrainVatReturn::STATUS_DRAFT,
                'filing_due_date' => $filingDue,
                'notes'           => $data['notes'] ?? null,
                'prepared_by'     => $userId,
            ]);

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            return BahrainVatReturn::create($attributes);
        });
    }

    /**
     * Submit a draft return to NBR.
     */
    public function submitReturn(BahrainVatReturn $return, ?string $nbrReference = null): BahrainVatReturn
    {
        if (!$return->isDraft()) {
            throw new InvalidArgumentException('Only draft returns can be submitted.');
        }

        $return->update([
            'status'        => BahrainVatReturn::STATUS_SUBMITTED,
            'nbr_reference' => $nbrReference,
            'filed_at'      => now()->toDateString(),
        ]);

        return $return->fresh();
    }

    /**
     * Generate a CSV export in NBR return format.
     *
     * Columns: Box, Description, Amount (BHD)
     */
    public function exportCsv(BahrainVatReturn $return): string
    {
        $rows = [
            ['Box', 'Description', 'Amount (BHD)'],
            ['1', 'Standard-rated supplies', number_format($return->standard_rated_supplies, 2)],
            ['2', 'Zero-rated supplies',      number_format($return->zero_rated_supplies, 2)],
            ['3', 'Exempt supplies',           number_format($return->exempt_supplies, 2)],
            ['4', 'Output VAT',               number_format($return->output_vat, 2)],
            ['5', 'Standard-rated purchases', number_format($return->standard_rated_purchases, 2)],
            ['6', 'Capital goods input tax',  number_format($return->capital_goods_input_tax, 2)],
            ['7', 'Total input VAT',           number_format($return->total_input_vat, 2)],
            ['8', 'Net VAT payable',           number_format($return->net_vat_payable, 2)],
        ];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return $csv;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{0: string, 1: string} */
    private function resolvePeriodDates(int $year, ?int $quarter, ?int $month): array
    {
        if ($quarter !== null) {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth   = $startMonth + 2;
            $endDay     = (int) date('t', mktime(0, 0, 0, $endMonth, 1, $year));
            return [
                date('Y-m-d', mktime(0, 0, 0, $startMonth, 1, $year)),
                date('Y-m-d', mktime(0, 0, 0, $endMonth, $endDay, $year)),
            ];
        }

        // Monthly
        $endDay = (int) date('t', mktime(0, 0, 0, $month ?? 1, 1, $year));
        return [
            date('Y-m-d', mktime(0, 0, 0, $month ?? 1, 1, $year)),
            date('Y-m-d', mktime(0, 0, 0, $month ?? 1, $endDay, $year)),
        ];
    }

    private function resolveFilingDue(int $year, ?int $quarter, ?int $month): string
    {
        if ($quarter !== null) {
            [$dueMonth, $dueDay] = self::QUARTERLY_DUE[$quarter];
            $dueYear = $quarter === 4 ? $year + 1 : $year;
            return date('Y-m-d', mktime(0, 0, 0, $dueMonth, $dueDay, $dueYear));
        }

        // Monthly: last day of following month
        $followMonth = ($month ?? 1) + 1;
        $followYear  = $year;
        if ($followMonth > 12) {
            $followMonth = 1;
            $followYear++;
        }
        $lastDay = (int) date('t', mktime(0, 0, 0, $followMonth, 1, $followYear));
        return date('Y-m-d', mktime(0, 0, 0, $followMonth, $lastDay, $followYear));
    }
}
