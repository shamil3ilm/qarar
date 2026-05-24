<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\Gstr1Return;
use App\Models\Tax\Gstr3bReturn;
use App\Models\Tax\Gstr9Return;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GSTR-9 Annual Return Service.
 *
 * Prepares the annual GST return by aggregating data from:
 *   - GSTR-1 monthly returns (outward supplies, Table 4)
 *   - GSTR-3B monthly returns (tax paid + ITC, Tables 6/7/9)
 *
 * Filing due date: 31 December of the year following the FY end.
 * (e.g., FY 2024-25 → due 31 December 2025)
 */
class Gstr9ReturnService
{
    // -------------------------------------------------------------------------
    // Aggregate from monthly returns
    // -------------------------------------------------------------------------

    /**
     * Prepare GSTR-9 by aggregating GSTR-1 + GSTR-3B monthly data.
     *
     * @param  int    $organizationId
     * @param  string $gstin            15-char GSTIN
     * @param  int    $fyStart          Financial year start (e.g. 2024 for FY 2024-25)
     * @param  int    $userId
     */
    public function prepareFromMonthly(
        int $organizationId,
        string $gstin,
        int $fyStart,
        int $userId,
    ): Gstr9Return {
        return DB::transaction(function () use ($organizationId, $gstin, $fyStart, $userId) {
            $existing = Gstr9Return::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('gstin', $gstin)
                ->where('financial_year_start', $fyStart)
                ->first();

            if ($existing && !$existing->isDraft()) {
                throw new InvalidArgumentException(
                    "GSTR-9 for {$gstin} FY {$fyStart} already exists with status '{$existing->status}'."
                );
            }

            [$fyStartDate, $fyEndDate] = $this->fyDates($fyStart);

            // Aggregate GSTR-1 data (outward supplies)
            $gstr1Totals = $this->aggregateGstr1($organizationId, $gstin, $fyStartDate, $fyEndDate);

            // Aggregate GSTR-3B data (tax paid + ITC)
            $gstr3bTotals = $this->aggregateGstr3b($organizationId, $gstin, $fyStartDate, $fyEndDate);

            $totalItc    = round(
                $gstr3bTotals['itc_inputs'] +
                $gstr3bTotals['itc_input_services'] +
                $gstr3bTotals['itc_capital_goods'],
                2
            );
            $netItc = max(0.0, round($totalItc - $gstr3bTotals['itc_reversed'], 2));

            $dueDate = date('Y-m-d', mktime(0, 0, 0, 12, 31, $fyStart + 1));

            $attributes = [
                'organization_id'       => $organizationId,
                'gstin'                 => $gstin,
                'financial_year_start'  => $fyStart,

                // Table 4: outward supplies from GSTR-1
                't4a_taxable_supplies'  => $gstr1Totals['taxable_value'],
                't4b_zero_rated'        => $gstr1Totals['zero_rated'],
                't4c_nil_rated'         => $gstr1Totals['nil_rated'],

                // Table 9: tax from GSTR-3B
                't9_igst_payable'       => $gstr3bTotals['igst_payable'],
                't9_cgst_payable'       => $gstr3bTotals['cgst_payable'],
                't9_sgst_payable'       => $gstr3bTotals['sgst_payable'],
                't9_cess_payable'       => $gstr3bTotals['cess_payable'],
                't9_igst_paid'          => $gstr3bTotals['igst_paid'],
                't9_cgst_paid'          => $gstr3bTotals['cgst_paid'],
                't9_sgst_paid'          => $gstr3bTotals['sgst_paid'],
                't9_cess_paid'          => $gstr3bTotals['cess_paid'],

                // Table 6: ITC from GSTR-3B
                't6a_itc_inputs'        => $gstr3bTotals['itc_inputs'],
                't6b_itc_input_services' => $gstr3bTotals['itc_input_services'],
                't6c_itc_capital_goods' => $gstr3bTotals['itc_capital_goods'],
                't6_total_itc'          => $totalItc,

                // Table 7: reversed ITC
                't7_itc_reversed'       => $gstr3bTotals['itc_reversed'],
                'net_itc'               => $netItc,

                'status'                => Gstr9Return::STATUS_DRAFT,
                'due_date'              => $dueDate,
                'notes'                 => null,
                'prepared_by'           => $userId,
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            return Gstr9Return::create($attributes);
        });
    }

    /**
     * Prepare GSTR-9 from explicit data (when monthly returns aren't in the system).
     *
     * @param  array  $data  Must include: organization_id, gstin, financial_year_start.
     *                       All Table 4/6/7/9 fields are optional (default 0).
     */
    public function createManual(array $data, int $userId): Gstr9Return
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId   = (int) $data['organization_id'];
            $gstin   = (string) $data['gstin'];
            $fyStart = (int) $data['financial_year_start'];

            if (strlen($gstin) !== 15) {
                throw new InvalidArgumentException("GSTIN must be 15 characters. Got: '{$gstin}'.");
            }

            $existing = Gstr9Return::withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->where('gstin', $gstin)
                ->where('financial_year_start', $fyStart)
                ->first();

            if ($existing && !$existing->isDraft()) {
                throw new InvalidArgumentException(
                    "GSTR-9 for {$gstin} FY {$fyStart} already exists with status '{$existing->status}'."
                );
            }

            $t6Total = round(
                (float) ($data['t6a_itc_inputs'] ?? 0) +
                (float) ($data['t6b_itc_input_services'] ?? 0) +
                (float) ($data['t6c_itc_capital_goods'] ?? 0),
                2
            );
            $netItc = max(0.0, round($t6Total - (float) ($data['t7_itc_reversed'] ?? 0), 2));
            $dueDate = date('Y-m-d', mktime(0, 0, 0, 12, 31, $fyStart + 1));

            $attributes = [
                'organization_id'        => $orgId,
                'gstin'                  => $gstin,
                'financial_year_start'   => $fyStart,
                't4a_taxable_supplies'   => (float) ($data['t4a_taxable_supplies'] ?? 0),
                't4b_zero_rated'         => (float) ($data['t4b_zero_rated'] ?? 0),
                't4c_nil_rated'          => (float) ($data['t4c_nil_rated'] ?? 0),
                't9_igst_payable'        => (float) ($data['t9_igst_payable'] ?? 0),
                't9_cgst_payable'        => (float) ($data['t9_cgst_payable'] ?? 0),
                't9_sgst_payable'        => (float) ($data['t9_sgst_payable'] ?? 0),
                't9_cess_payable'        => (float) ($data['t9_cess_payable'] ?? 0),
                't9_igst_paid'           => (float) ($data['t9_igst_paid'] ?? 0),
                't9_cgst_paid'           => (float) ($data['t9_cgst_paid'] ?? 0),
                't9_sgst_paid'           => (float) ($data['t9_sgst_paid'] ?? 0),
                't9_cess_paid'           => (float) ($data['t9_cess_paid'] ?? 0),
                't6a_itc_inputs'         => (float) ($data['t6a_itc_inputs'] ?? 0),
                't6b_itc_input_services' => (float) ($data['t6b_itc_input_services'] ?? 0),
                't6c_itc_capital_goods'  => (float) ($data['t6c_itc_capital_goods'] ?? 0),
                't6_total_itc'           => $t6Total,
                't7_itc_reversed'        => (float) ($data['t7_itc_reversed'] ?? 0),
                'net_itc'                => $netItc,
                't18_late_fee_cgst'      => (float) ($data['t18_late_fee_cgst'] ?? 0),
                't18_late_fee_sgst'      => (float) ($data['t18_late_fee_sgst'] ?? 0),
                'status'                 => Gstr9Return::STATUS_DRAFT,
                'due_date'               => $dueDate,
                'notes'                  => $data['notes'] ?? null,
                'prepared_by'            => $userId,
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            return Gstr9Return::create($attributes);
        });
    }

    /**
     * File a draft GSTR-9 with the GSTN portal.
     */
    public function fileReturn(Gstr9Return $return, ?string $arn = null): Gstr9Return
    {
        if (!$return->isDraft()) {
            throw new InvalidArgumentException('Only draft GSTR-9 returns can be filed.');
        }

        $return->update([
            'status'     => Gstr9Return::STATUS_FILED,
            'gstn_arn'   => $arn,
            'filed_date' => now()->toDateString(),
        ]);

        return $return->fresh();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{0: string, 1: string} [fyStartDate, fyEndDate] */
    private function fyDates(int $fyStart): array
    {
        return [
            "{$fyStart}-04-01",
            ($fyStart + 1) . '-03-31',
        ];
    }

    /** @return array<string, float> */
    private function aggregateGstr1(int $orgId, string $gstin, string $from, string $to): array
    {
        $rows = Gstr1Return::where('organization_id', $orgId)
            ->where('gstin_id', function ($q) use ($orgId, $gstin) {
                $q->select('id')
                    ->from('gst_registrations')
                    ->where('organization_id', $orgId)
                    ->where('gstin', $gstin)
                    ->limit(1);
            })
            ->whereBetween(DB::raw("DATE(CONCAT(period_year, '-', LPAD(period_month, 2, '0'), '-01'))"), [$from, $to])
            ->selectRaw('
                COALESCE(SUM(total_taxable_value), 0) as taxable_value,
                0 as zero_rated,
                0 as nil_rated
            ')
            ->first();

        return [
            'taxable_value' => (float) ($rows?->taxable_value ?? 0),
            'zero_rated'    => 0.0,
            'nil_rated'     => 0.0,
        ];
    }

    /** @return array<string, float> */
    private function aggregateGstr3b(int $orgId, string $gstin, string $from, string $to): array
    {
        $rows = Gstr3bReturn::where('organization_id', $orgId)
            ->where('gstin_id', function ($q) use ($orgId, $gstin) {
                $q->select('id')
                    ->from('gst_registrations')
                    ->where('organization_id', $orgId)
                    ->where('gstin', $gstin)
                    ->limit(1);
            })
            ->whereBetween(DB::raw("DATE(CONCAT(period_year, '-', LPAD(period_month, 2, '0'), '-01'))"), [$from, $to])
            ->selectRaw('
                COALESCE(SUM(igst_payable), 0)      as igst_payable,
                COALESCE(SUM(cgst_payable), 0)      as cgst_payable,
                COALESCE(SUM(sgst_payable), 0)      as sgst_payable,
                0                                   as cess_payable,
                COALESCE(SUM(igst_paid), 0)         as igst_paid,
                COALESCE(SUM(cgst_paid), 0)         as cgst_paid,
                COALESCE(SUM(sgst_paid), 0)         as sgst_paid,
                0                                   as cess_paid,
                COALESCE(SUM(itc_igst), 0)          as itc_inputs,
                0                                   as itc_input_services,
                0                                   as itc_capital_goods,
                0                                   as itc_reversed
            ')
            ->first();

        return [
            'igst_payable'        => (float) ($rows?->igst_payable ?? 0),
            'cgst_payable'        => (float) ($rows?->cgst_payable ?? 0),
            'sgst_payable'        => (float) ($rows?->sgst_payable ?? 0),
            'cess_payable'        => 0.0,
            'igst_paid'           => (float) ($rows?->igst_paid ?? 0),
            'cgst_paid'           => (float) ($rows?->cgst_paid ?? 0),
            'sgst_paid'           => (float) ($rows?->sgst_paid ?? 0),
            'cess_paid'           => 0.0,
            'itc_inputs'          => (float) ($rows?->itc_inputs ?? 0),
            'itc_input_services'  => 0.0,
            'itc_capital_goods'   => 0.0,
            'itc_reversed'        => 0.0,
        ];
    }
}
