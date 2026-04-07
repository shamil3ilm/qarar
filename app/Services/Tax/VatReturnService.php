<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\VatReturnBox;
use App\Models\Tax\VatReturnPeriod;
use App\Models\Tax\VatTransaction;
use Illuminate\Support\Facades\DB;

class VatReturnService
{
    /**
     * Prepare (create or retrieve) a VAT return period.
     */
    public function preparePeriod(
        Organization $organization,
        string $countryCode,
        string $periodStart,
        string $periodEnd
    ): VatReturnPeriod {
        $existing = VatReturnPeriod::where('organization_id', $organization->id)
            ->where('country_code', $countryCode)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return VatReturnPeriod::create([
            'organization_id' => $organization->id,
            'country_code'    => $countryCode,
            'period_start'    => $periodStart,
            'period_end'      => $periodEnd,
            'status'          => 'draft',
        ]);
    }

    /**
     * Build VAT return boxes by aggregating transactions for the period.
     */
    public function buildReturnBoxes(VatReturnPeriod $period): VatReturnPeriod
    {
        // Include sale types and their reversal types (refund, credit_note, return).
        // VatTransaction stores taxable_amount and vat_amount as negative values for
        // reversal types, so they are simply aggregated alongside regular sales.
        $transactions = VatTransaction::where('organization_id', $period->organization_id)
            ->where('country_code', $period->country_code)
            ->whereBetween('tax_period', [
                $period->period_start->toDateString(),
                $period->period_end->toDateString(),
            ])
            ->whereIn('transaction_type', ['sale', 'purchase', 'refund', 'credit_note', 'return'])
            ->get();

        $outputTaxable = '0';
        $outputVat     = '0';
        $inputTaxable  = '0';
        $inputVat      = '0';
        $zeroRated     = '0';
        $exempt        = '0';

        foreach ($transactions as $txn) {
            if (in_array($txn->transaction_type, ['refund', 'return', 'credit_note'], true) && bccomp((string)$txn->taxable_amount, '0', 4) > 0) {
                throw new \InvalidArgumentException('Refund/return/credit note amounts must be negative.');
            }

            if (in_array($txn->transaction_type, ['sale', 'refund', 'credit_note', 'return'], true)) {
                if ($txn->is_exempt) {
                    $exempt = bcadd($exempt, (string) $txn->taxable_amount, 4);
                } elseif ($txn->is_zero_rated) {
                    $zeroRated = bcadd($zeroRated, (string) $txn->taxable_amount, 4);
                } else {
                    $outputTaxable = bcadd($outputTaxable, (string) $txn->taxable_amount, 4);
                    $outputVat     = bcadd($outputVat, (string) $txn->vat_amount, 4);
                }
            } elseif ($txn->transaction_type === 'purchase') {
                $inputTaxable = bcadd($inputTaxable, (string) $txn->taxable_amount, 4);
                $inputVat     = bcadd($inputVat, (string) $txn->vat_amount, 4);
            }
        }

        $netVat = bcsub($outputVat, $inputVat, 4);

        $boxes = [
            ['box_number' => '1',  'box_label' => 'Standard rated supplies',           'output_amount' => (float) $outputTaxable, 'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '2',  'box_label' => 'Zero rated supplies',               'output_amount' => (float) $zeroRated,     'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '3',  'box_label' => 'Exempt supplies',                   'output_amount' => (float) $exempt,        'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '4',  'box_label' => 'VAT on standard rated supplies',    'output_amount' => (float) $outputVat,     'input_amount' => 0,                  'net_vat' => (float) $outputVat],
            ['box_number' => '5',  'box_label' => 'Standard rated purchases',          'output_amount' => 0,                      'input_amount' => (float) $inputTaxable, 'net_vat' => 0],
            ['box_number' => '6',  'box_label' => 'VAT on standard rated purchases',   'output_amount' => 0,                      'input_amount' => (float) $inputVat,  'net_vat' => (float) $inputVat],
            ['box_number' => '7',  'box_label' => 'Net VAT due / (refundable)',        'output_amount' => (float) $outputVat,     'input_amount' => (float) $inputVat,  'net_vat' => (float) $netVat],
        ];

        DB::transaction(function () use ($period, $boxes): void {
            $period->boxes()->delete();

            foreach ($boxes as $box) {
                VatReturnBox::create(array_merge($box, [
                    'vat_return_period_id' => $period->id,
                ]));
            }

            $period->update(['status' => 'ready']);
        });

        return $period->fresh(['boxes']);
    }

    /**
     * Mark a VAT return as submitted.
     */
    public function submitReturn(VatReturnPeriod $period, ?string $referenceNumber = null): VatReturnPeriod
    {
        if (!$period->isDraft() && $period->status !== 'ready') {
            throw new \RuntimeException('Only draft or ready returns can be submitted.');
        }

        $period->update([
            'status'           => 'submitted',
            'submitted_at'     => now(),
            'reference_number' => $referenceNumber,
        ]);

        return $period->fresh();
    }

    /**
     * Record a VAT transaction for later aggregation.
     */
    public function recordTransaction(array $data): VatTransaction
    {
        return VatTransaction::create($data);
    }

    /**
     * Get VAT reconciliation summary for a period.
     */
    public function getReconciliationSummary(
        int $organizationId,
        string $countryCode,
        string $periodStart,
        string $periodEnd
    ): array {
        $transactions = VatTransaction::where('organization_id', $organizationId)
            ->where('country_code', $countryCode)
            ->whereBetween('tax_period', [$periodStart, $periodEnd])
            ->get();

        $output = $transactions->where('transaction_type', 'sale');
        $input  = $transactions->where('transaction_type', 'purchase');

        return [
            'period_start'          => $periodStart,
            'period_end'            => $periodEnd,
            'country_code'          => $countryCode,
            'output_taxable_amount' => $output->sum('taxable_amount'),
            'output_vat'            => $output->sum('vat_amount'),
            'input_taxable_amount'  => $input->sum('taxable_amount'),
            'input_vat'             => $input->sum('vat_amount'),
            'net_vat_payable'       => (float) bcsub(
                (string) $output->sum('vat_amount'),
                (string) $input->sum('vat_amount'),
                4
            ),
            'zero_rated_supplies'   => $output->where('is_zero_rated', true)->sum('taxable_amount'),
            'exempt_supplies'       => $output->where('is_exempt', true)->sum('taxable_amount'),
        ];
    }
}
