<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Tax\Ewaybill;
use App\Models\Tax\GstRegistration;
use App\Models\Tax\Gstr1B2bInvoice;
use App\Models\Tax\Gstr1Return;
use App\Models\Tax\Gstr3bReturn;
use App\Models\Tax\ItcLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GstReturnService
{
    /**
     * Prepare a GSTR-1 return for a given registration and period.
     */
    public function prepareGstr1(
        GstRegistration $registration,
        int $month,
        int $year,
        string $filingType = 'monthly'
    ): Gstr1Return {
        $existing = Gstr1Return::where('gstin_id', $registration->id)
            ->forPeriod($month, $year)
            ->first();

        if ($existing !== null && $existing->isFiled()) {
            throw new \RuntimeException('GSTR-1 for this period has already been filed.');
        }

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($registration, $month, $year, $filingType): Gstr1Return {
            $return = Gstr1Return::create([
                'organization_id'     => $registration->organization_id,
                'gstin_id'            => $registration->id,
                'period_month'        => $month,
                'period_year'         => $year,
                'filing_type'         => $filingType,
                'status'              => 'draft',
                'total_taxable_value' => 0,
                'total_igst'          => 0,
                'total_cgst'          => 0,
                'total_sgst'          => 0,
                'total_cess'          => 0,
            ]);

            return $return;
        });
    }

    /**
     * Add B2B invoice data to GSTR-1 and recalculate totals.
     */
    public function addB2bInvoice(Gstr1Return $return, array $invoiceData): Gstr1B2bInvoice
    {
        if ($return->isFiled()) {
            throw new \RuntimeException('Cannot modify a filed GSTR-1 return.');
        }

        return DB::transaction(function () use ($return, $invoiceData): Gstr1B2bInvoice {
            // Lock the return row first to prevent concurrent modifications
            $lockedReturn = Gstr1Return::lockForUpdate()->findOrFail($return->id);

            $invoice = Gstr1B2bInvoice::create(array_merge($invoiceData, [
                'gstr1_return_id' => $lockedReturn->id,
            ]));

            $this->recalculateGstr1Totals($lockedReturn);

            return $invoice;
        });
    }

    /**
     * Recalculate GSTR-1 totals from B2B invoices.
     *
     * Fetches the return with a pessimistic lock so that concurrent
     * addB2bInvoice() calls cannot overwrite each other's totals.
     */
    private function recalculateGstr1Totals(Gstr1Return $return): void
    {
        DB::transaction(function () use ($return): void {
            // Lock the return row to prevent concurrent recalculation races.
            $lockedReturn = Gstr1Return::where('id', $return->id)->lockForUpdate()->first();

            if ($lockedReturn === null) {
                return;
            }

            $totals = Gstr1B2bInvoice::where('gstr1_return_id', $lockedReturn->id)
                ->selectRaw('
                    SUM(taxable_value) as total_taxable_value,
                    SUM(igst)          as total_igst,
                    SUM(cgst)          as total_cgst,
                    SUM(sgst)          as total_sgst,
                    SUM(cess)          as total_cess
                ')
                ->first();

            $lockedReturn->update([
                'total_taxable_value' => $totals->total_taxable_value ?? 0,
                'total_igst'          => $totals->total_igst ?? 0,
                'total_cgst'          => $totals->total_cgst ?? 0,
                'total_sgst'          => $totals->total_sgst ?? 0,
                'total_cess'          => $totals->total_cess ?? 0,
                'status'              => 'ready',
            ]);
        });
    }

    /**
     * Prepare a GSTR-3B return.
     */
    public function prepareGstr3b(
        GstRegistration $registration,
        int $month,
        int $year
    ): Gstr3bReturn {
        $existing = Gstr3bReturn::where('gstin_id', $registration->id)
            ->forPeriod($month, $year)
            ->first();

        if ($existing !== null && $existing->isFiled()) {
            throw new \RuntimeException('GSTR-3B for this period has already been filed.');
        }

        if ($existing !== null) {
            return $existing;
        }

        // Derive figures from GSTR-1 if available
        $gstr1 = Gstr1Return::where('gstin_id', $registration->id)
            ->forPeriod($month, $year)
            ->first();

        $outwardTaxable = $gstr1 ? (float) $gstr1->total_taxable_value : 0;
        $outwardZero    = 0;

        // Auto-initialize ITC ledger from eligible purchase transactions if no
        // ledger entry exists yet, so GSTR-3B does not show ITC = 0 by default.
        if (!ItcLedger::where('organization_id', $registration->organization_id)
            ->where('gstin_id', $registration->id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->exists()
        ) {
            $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $periodEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

            $igstItc = (string) \App\Models\Tax\VatTransaction::where('organization_id', $registration->organization_id)
                ->where('transaction_type', 'purchase')
                ->whereBetween('tax_period', [$periodStart, $periodEnd])
                ->sum('vat_amount');

            ItcLedger::create([
                'organization_id' => $registration->organization_id,
                'gstin_id'        => $registration->id,
                'period_month'    => $month,
                'period_year'     => $year,
                'igst_available'  => $igstItc,
                'cgst_available'  => 0,
                'sgst_available'  => 0,
                'igst_utilized'   => 0,
                'cgst_utilized'   => 0,
                'sgst_utilized'   => 0,
                'igst_closing'    => $igstItc,
                'cgst_closing'    => 0,
                'sgst_closing'    => 0,
            ]);
        }

        // Calculate ITC available for the period
        $itcData       = $this->calculateItcAvailable($registration, $month, $year);
        $itcAvailable  = (float) bcadd(
            bcadd((string) $itcData['igst_available'], (string) $itcData['cgst_available'], 4),
            (string) $itcData['sgst_available'],
            4
        );

        $totalOutputTax = $gstr1
            ? (float) bcadd(
                bcadd((string) $gstr1->total_igst, (string) $gstr1->total_cgst, 4),
                (string) $gstr1->total_sgst,
                4
            )
            : 0;

        $netTaxDiff = bcsub((string) $totalOutputTax, (string) $itcAvailable, 4);
        $netTax     = bccomp($netTaxDiff, '0', 4) > 0 ? $netTaxDiff : '0.0000';

        return Gstr3bReturn::create([
            'organization_id'          => $registration->organization_id,
            'gstin_id'                 => $registration->id,
            'period_month'             => $month,
            'period_year'              => $year,
            'outward_taxable_supplies' => $outwardTaxable,
            'outward_zero_rated'       => $outwardZero,
            'inward_supplies_itc'      => $itcAvailable,
            'net_tax_payable'          => $netTax,
            'status'                   => 'draft',
        ]);
    }

    /**
     * Generate an e-way bill.
     */
    public function generateEwayBill(array $data): Ewaybill
    {
        // Validate mandatory fields
        $required = ['organization_id', 'gstin_supplier', 'gstin_recipient', 'supply_type', 'distance_km'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required for e-way bill generation.");
            }
        }

        return Ewaybill::create(array_merge($data, [
            'status'       => 'generated',
            'generated_at' => now(),
            'valid_until'  => now()->addDays($this->computeValidityDays((int) $data['distance_km'])),
        ]));
    }

    /**
     * Cancel an e-way bill.
     */
    public function cancelEwayBill(Ewaybill $ewayBill, string $reason = ''): Ewaybill
    {
        if ($ewayBill->isCancelled()) {
            throw new \RuntimeException('E-way bill is already cancelled.');
        }

        $ewayBill->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $ewayBill->fresh();
    }

    /**
     * Calculate ITC available for a registration and period.
     */
    public function calculateItcAvailable(
        GstRegistration $registration,
        int $month,
        int $year
    ): array {
        $ledger = ItcLedger::where('gstin_id', $registration->id)
            ->forPeriod($month, $year)
            ->first();

        if ($ledger !== null) {
            return [
                'igst_available' => (float) $ledger->igst_available,
                'cgst_available' => (float) $ledger->cgst_available,
                'sgst_available' => (float) $ledger->sgst_available,
            ];
        }

        // Default: no ITC available yet
        return [
            'igst_available' => 0,
            'cgst_available' => 0,
            'sgst_available' => 0,
        ];
    }

    /**
     * Update ITC ledger entry.
     */
    public function updateItcLedger(
        GstRegistration $registration,
        int $month,
        int $year,
        array $data
    ): ItcLedger {
        return ItcLedger::updateOrCreate(
            [
                'organization_id' => $registration->organization_id,
                'gstin_id'        => $registration->id,
                'period_month'    => $month,
                'period_year'     => $year,
            ],
            $data
        );
    }

    /**
     * File a GSTR-1 return.
     */
    public function fileGstr1(Gstr1Return $return, string $arn): Gstr1Return
    {
        if ($return->isFiled()) {
            throw new \RuntimeException('GSTR-1 is already filed.');
        }

        $return->update([
            'status'   => 'filed',
            'filed_at' => now(),
            'arn'      => $arn,
        ]);

        return $return->fresh();
    }

    /**
     * File a GSTR-3B return.
     */
    public function fileGstr3b(Gstr3bReturn $return, string $arn): Gstr3bReturn
    {
        if ($return->isFiled()) {
            throw new \RuntimeException('GSTR-3B is already filed.');
        }

        $return->update([
            'status'   => 'filed',
            'filed_at' => now(),
            'arn'      => $arn,
        ]);

        return $return->fresh();
    }

    /**
     * Compute e-way bill validity in days based on distance.
     * Rules: 1–100 km → 1 day; 101–200 km → 1 day; 201–300 km → 2 days; etc.
     *
     * Using floor(($distance - 1) / 100) + 1 gives the correct band:
     *   1–100   → 1,  101–200 → 1,  201–300 → 2, …  (max 15)
     */
    private function computeValidityDays(int $distanceKm): int
    {
        if ($distanceKm <= 0) {
            return 1;
        }

        $days = (int) floor(($distanceKm - 1) / 100) + 1;

        return min($days, 15);
    }
}
