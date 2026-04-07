<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\VatReturnLineItem;
use App\Models\Tax\VatReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating and filing GCC VAT returns stored in the
 * vat_return_periods / vat_return_line_items tables (migration 2026_03_25_000002).
 *
 * This service is distinct from the existing VatReturnService, which manages
 * VatReturnBox and VatTransaction records from the prior schema.
 */
class VatComplianceService
{
    /**
     * Generate (or retrieve) a VAT return period and aggregate invoice/bill
     * data into summary line items.
     *
     * @param  Organization  $org
     * @param  string        $countryCode  ISO 3166-1 alpha-2 (e.g. 'AE', 'SA')
     * @param  Carbon        $start
     * @param  Carbon        $end
     * @return VatReturn
     */
    public function generateReturn(
        Organization $org,
        string $countryCode,
        Carbon $start,
        Carbon $end
    ): VatReturn {
        return DB::transaction(function () use ($org, $countryCode, $start, $end): VatReturn {
            $existing = VatReturn::withoutGlobalScope('organization')
                ->where('organization_id', $org->id)
                ->where('country_code', strtoupper($countryCode))
                ->where('period_start', $start->toDateString())
                ->where('period_end', $end->toDateString())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Aggregate output VAT (from invoices)
            $outputData = $this->aggregateOutputVat($org->id, $start, $end);

            // Aggregate input VAT (from bills)
            $inputData = $this->aggregateInputVat($org->id, $start, $end);

            $netVatPayable = (float) bcsub(
                (string) $outputData['vat_amount'],
                (string) $inputData['vat_amount'],
                4
            );

            $period = VatReturn::create([
                'organization_id' => $org->id,
                'country_code'    => strtoupper($countryCode),
                'period_start'    => $start->toDateString(),
                'period_end'      => $end->toDateString(),
                'status'          => 'draft',
                'total_sales'     => $outputData['taxable_amount'],
                'total_purchases' => $inputData['taxable_amount'],
                'output_vat'      => $outputData['vat_amount'],
                'input_vat'       => $inputData['vat_amount'],
                'net_vat_payable' => $netVatPayable,
            ]);

            // Create line items
            $this->createLineItems($period, $outputData, $inputData);

            return $period->load('lineItems');
        });
    }

    /**
     * File a VAT return: mark it as filed with the authenticated user.
     *
     * @param  VatReturn  $period
     * @param  User             $user
     * @return VatReturn
     *
     * @throws \RuntimeException if the period is already filed or paid.
     */
    public function fileReturn(VatReturn $period, User $user): VatReturn
    {
        if (in_array($period->status, ['filed', 'paid'], true)) {
            throw new \RuntimeException('VAT return has already been filed.');
        }

        $period->update([
            'status'    => 'filed',
            'filed_at'  => now(),
            'filed_by'  => $user->id,
        ]);

        return $period->fresh(['lineItems', 'filedBy']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Aggregate invoice-level VAT (output tax) for the period.
     */
    private function aggregateOutputVat(int $organizationId, Carbon $start, Carbon $end): array
    {
        $result = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.organization_id', $organizationId)
            ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('invoices.deleted_at')
            ->selectRaw('
                COALESCE(SUM(invoice_lines.unit_price * invoice_lines.quantity), 0) AS taxable_amount,
                COALESCE(SUM(invoice_lines.tax_amount), 0)                          AS vat_amount,
                COALESCE(SUM(CASE WHEN invoice_lines.tax_rate = 0 AND invoice_lines.is_exempt = 0 THEN invoice_lines.unit_price * invoice_lines.quantity ELSE 0 END), 0) AS zero_rated,
                COALESCE(SUM(CASE WHEN invoice_lines.is_exempt = 1 THEN invoice_lines.unit_price * invoice_lines.quantity ELSE 0 END), 0) AS exempt
            ')
            ->first();

        return [
            'taxable_amount' => (float) ($result->taxable_amount ?? 0),
            'vat_amount'     => (float) ($result->vat_amount ?? 0),
            'zero_rated'     => (float) ($result->zero_rated ?? 0),
            'exempt'         => (float) ($result->exempt ?? 0),
        ];
    }

    /**
     * Aggregate bill-level VAT (input tax / recoverable VAT) for the period.
     */
    private function aggregateInputVat(int $organizationId, Carbon $start, Carbon $end): array
    {
        $result = DB::table('bill_lines')
            ->join('bills', 'bills.id', '=', 'bill_lines.bill_id')
            ->where('bills.organization_id', $organizationId)
            ->whereBetween('bills.bill_date', [$start->toDateString(), $end->toDateString()])
            ->whereNull('bills.deleted_at')
            ->selectRaw('
                COALESCE(SUM(bill_lines.unit_price * bill_lines.quantity), 0) AS taxable_amount,
                COALESCE(SUM(bill_lines.tax_amount), 0)                       AS vat_amount
            ')
            ->first();

        return [
            'taxable_amount' => (float) ($result->taxable_amount ?? 0),
            'vat_amount'     => (float) ($result->vat_amount ?? 0),
            'zero_rated'     => 0.0,
            'exempt'         => 0.0,
        ];
    }

    /**
     * Create vat_return_line_items rows for the given period.
     */
    private function createLineItems(
        VatReturn $period,
        array $outputData,
        array $inputData
    ): void {
        $standardRatedAmount = (float) bcsub(
            bcsub((string) $outputData['taxable_amount'], (string) $outputData['zero_rated'], 4),
            (string) $outputData['exempt'],
            4
        );

        $lines = [
            [
                'line_type'  => 'standard_rated_supply',
                'amount'     => max(0.0, $standardRatedAmount),
                'vat_amount' => $outputData['vat_amount'],
                'adjustment' => 0,
            ],
            [
                'line_type'  => 'zero_rated_supply',
                'amount'     => $outputData['zero_rated'],
                'vat_amount' => 0,
                'adjustment' => 0,
            ],
            [
                'line_type'  => 'exempt_supply',
                'amount'     => $outputData['exempt'],
                'vat_amount' => 0,
                'adjustment' => 0,
            ],
            [
                'line_type'  => 'standard_rated_purchase',
                'amount'     => $inputData['taxable_amount'],
                'vat_amount' => $inputData['vat_amount'],
                'adjustment' => 0,
            ],
        ];

        foreach ($lines as $line) {
            VatReturnLineItem::create(array_merge($line, [
                'vat_return_period_id' => $period->id,
            ]));
        }
    }
}
