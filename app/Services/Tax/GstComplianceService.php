<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\GstReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating and filing consolidated GST returns stored in
 * the gst_returns table (migration 2026_03_25_000002).
 *
 * This service is distinct from GstReturnService, which manages GSTIN
 * registrations, GSTR-1 B2B line items, GSTR-3B detail fields, e-way
 * bills, and the ITC ledger via their own dedicated tables.
 */
class GstComplianceService
{
    /**
     * Generate a GSTR-1 summary return for the given organization, year and month.
     * Aggregates invoice-level GST components into top-level totals.
     *
     * @param  Organization  $org
     * @param  int           $year   Calendar year (e.g. 2025)
     * @param  int           $month  Month number 1–12
     * @return GstReturn
     */
    public function generateGstr1(Organization $org, int $year, int $month): GstReturn
    {
        return DB::transaction(function () use ($org, $year, $month): GstReturn {
            $existing = GstReturn::withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('return_type', 'gstr1')
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Aggregate invoice line tax components for the period
            $periodStart = sprintf('%04d-%02d-01', $year, $month);
            $periodEnd   = date('Y-m-t', strtotime($periodStart));

            $totals = $this->aggregateInvoiceTotals($org->id, $periodStart, $periodEnd);

            return GstReturn::create([
                'organization_id'     => $org->id,
                'return_type'         => 'gstr1',
                'period_year'         => $year,
                'period_month'        => $month,
                'status'              => 'draft',
                'total_taxable_value' => $totals['taxable_value'],
                'total_cgst'          => $totals['cgst'],
                'total_sgst'          => $totals['sgst'],
                'total_igst'          => $totals['igst'],
                'total_cess'          => $totals['cess'],
            ]);
        });
    }

    /**
     * Generate a GSTR-3B summary return for the given organization, year and month.
     * GSTR-3B is a self-declaration of summary GST liabilities.
     *
     * @param  Organization  $org
     * @param  int           $year
     * @param  int           $month
     * @return GstReturn
     */
    public function generateGstr3b(Organization $org, int $year, int $month): GstReturn
    {
        return DB::transaction(function () use ($org, $year, $month): GstReturn {
            $existing = GstReturn::withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('return_type', 'gstr3b')
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $periodStart = sprintf('%04d-%02d-01', $year, $month);
            $periodEnd   = date('Y-m-t', strtotime($periodStart));

            $outwardTotals  = $this->aggregateInvoiceTotals($org->id, $periodStart, $periodEnd);
            $inwardTotals   = $this->aggregatePurchaseTotals($org->id, $periodStart, $periodEnd);

            // Net IGST: outward liability minus inward ITC credit
            $igstDiff = bcsub((string) $outwardTotals['igst'], (string) $inwardTotals['igst'], 4);
            $netIgst  = bccomp($igstDiff, '0', 4) > 0 ? $igstDiff : '0.0000';
            $cgstDiff = bcsub((string) $outwardTotals['cgst'], (string) $inwardTotals['cgst'], 4);
            $netCgst  = bccomp($cgstDiff, '0', 4) > 0 ? $cgstDiff : '0.0000';
            $sgstDiff = bcsub((string) $outwardTotals['sgst'], (string) $inwardTotals['sgst'], 4);
            $netSgst  = bccomp($sgstDiff, '0', 4) > 0 ? $sgstDiff : '0.0000';

            return GstReturn::create([
                'organization_id'     => $org->id,
                'return_type'         => 'gstr3b',
                'period_year'         => $year,
                'period_month'        => $month,
                'status'              => 'draft',
                'total_taxable_value' => $outwardTotals['taxable_value'],
                'total_cgst'          => $netCgst,
                'total_sgst'          => $netSgst,
                'total_igst'          => $netIgst,
                'total_cess'          => (function () use ($outwardTotals, $inwardTotals): string {
                    $diff = bcsub((string) $outwardTotals['cess'], (string) $inwardTotals['cess'], 4);
                    return bccomp($diff, '0', 4) > 0 ? $diff : '0.0000';
                })(),
            ]);
        });
    }

    /**
     * File a GST return: mark it as filed with the authenticated user and timestamp.
     *
     * @param  GstReturn  $return
     * @param  User       $user
     * @return GstReturn
     *
     * @throws \RuntimeException if the return has already been filed.
     */
    public function fileReturn(GstReturn $return, User $user): GstReturn
    {
        if ($return->isFiled()) {
            throw new \RuntimeException('This GST return has already been filed.');
        }

        $isLate = $this->isLate($return);

        $return->update([
            'status'    => $isLate ? 'late_filed' : 'filed',
            'filed_at'  => now(),
            'filed_by'  => $user->id,
        ]);

        return $return->fresh();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Aggregate invoice-level GST components (output tax) for a period.
     */
    private function aggregateInvoiceTotals(int $organizationId, string $start, string $end): array
    {
        $result = \DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.organization_id', $organizationId)
            ->whereBetween('invoices.invoice_date', [$start, $end])
            ->whereNull('invoices.deleted_at')
            ->selectRaw('
                COALESCE(SUM(CAST(invoice_lines.unit_price AS DECIMAL(15,4)) * CAST(invoice_lines.quantity AS DECIMAL(15,4))), 0) AS taxable_value,
                COALESCE(SUM(invoice_lines.cgst_amount), 0)                              AS cgst,
                COALESCE(SUM(invoice_lines.sgst_amount), 0)                              AS sgst,
                COALESCE(SUM(invoice_lines.igst_amount), 0)                              AS igst,
                COALESCE(SUM(invoice_lines.cess_amount), 0)                              AS cess
            ')
            ->first();

        return [
            'taxable_value' => (string) ($result->taxable_value ?? '0'),
            'cgst'          => (string) ($result->cgst ?? '0'),
            'sgst'          => (string) ($result->sgst ?? '0'),
            'igst'          => (string) ($result->igst ?? '0'),
            'cess'          => (string) ($result->cess ?? '0'),
        ];
    }

    /**
     * Aggregate purchase order / bill GST components (input ITC) for a period.
     */
    private function aggregatePurchaseTotals(int $organizationId, string $start, string $end): array
    {
        $result = \DB::table('bill_lines')
            ->join('bills', 'bills.id', '=', 'bill_lines.bill_id')
            ->where('bills.organization_id', $organizationId)
            ->whereBetween('bills.bill_date', [$start, $end])
            ->whereNull('bills.deleted_at')
            ->selectRaw('
                COALESCE(SUM(CAST(bill_lines.unit_price AS DECIMAL(15,4)) * CAST(bill_lines.quantity AS DECIMAL(15,4))), 0) AS taxable_value,
                COALESCE(SUM(bill_lines.cgst_amount), 0)                        AS cgst,
                COALESCE(SUM(bill_lines.sgst_amount), 0)                        AS sgst,
                COALESCE(SUM(bill_lines.igst_amount), 0)                        AS igst,
                COALESCE(SUM(bill_lines.cess_amount), 0)                        AS cess
            ')
            ->first();

        return [
            'taxable_value' => (string) ($result->taxable_value ?? '0'),
            'cgst'          => (string) ($result->cgst ?? '0'),
            'sgst'          => (string) ($result->sgst ?? '0'),
            'igst'          => (string) ($result->igst ?? '0'),
            'cess'          => (string) ($result->cess ?? '0'),
        ];
    }

    /**
     * Determine whether a filing is late.
     * Standard GSTR-1 and GSTR-3B due date: 20th of the following month.
     */
    private function isLate(GstReturn $return): bool
    {
        if ($return->period_month === null) {
            return false;
        }

        $dueMonth = $return->period_month === 12 ? 1 : $return->period_month + 1;
        $dueYear  = $return->period_month === 12 ? $return->period_year + 1 : $return->period_year;
        $dueDate  = \Carbon\Carbon::create($dueYear, $dueMonth, 20, 23, 59, 59);

        return now()->isAfter($dueDate);
    }
}
