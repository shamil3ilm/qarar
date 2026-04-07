<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Sales\Invoice;
use App\Models\Tax\EwayBillRecord;
use Illuminate\Support\Facades\DB;

class EwayBillService
{
    /**
     * Generate a new e-way bill for an invoice.
     *
     * @param  Invoice  $invoice   The sales invoice to generate the bill for.
     * @param  array    $data      Transport and supply details.
     * @return EwayBillRecord
     */
    public function generate(Invoice $invoice, array $data): EwayBillRecord
    {
        return DB::transaction(function () use ($invoice, $data): EwayBillRecord {
            return EwayBillRecord::create([
                'organization_id'    => $invoice->organization_id,
                'invoice_id'         => $invoice->id,
                'eway_bill_number'   => $data['eway_bill_number'] ?? null,
                'transporter_name'   => $data['transporter_name'] ?? null,
                'transporter_id'     => $data['transporter_id'] ?? null,
                'vehicle_number'     => $data['vehicle_number'] ?? null,
                'transport_mode'     => $data['transport_mode'] ?? 'road',
                'distance_km'        => $data['distance_km'] ?? null,
                'supply_type'        => $data['supply_type'] ?? 'outward',
                'sub_supply_type'    => $data['sub_supply_type'] ?? null,
                'from_pincode'       => $data['from_pincode'] ?? null,
                'to_pincode'         => $data['to_pincode'] ?? null,
                'valid_upto'         => $data['valid_upto'] ?? $this->computeValidUpto($data['distance_km'] ?? 0),
                'status'             => 'active',
                'generated_at'       => now(),
            ]);
        });
    }

    /**
     * Cancel an active e-way bill.
     *
     * @param  EwayBillRecord  $bill
     * @return EwayBillRecord
     *
     * @throws \RuntimeException if the bill is already cancelled.
     */
    public function cancel(EwayBill $bill): EwayBillRecord
    {
        if ($bill->isCancelled()) {
            throw new \RuntimeException('E-way bill is already cancelled.');
        }

        $bill->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $bill->fresh();
    }

    /**
     * Compute e-way bill validity timestamp based on distance.
     * Rules (Indian e-way bill): <= 100 km → 1 day; each additional 100 km adds 1 day (max 15 days).
     */
    private function computeValidUpto(int $distanceKm): \Carbon\Carbon
    {
        $days = $distanceKm <= 100 ? 1 : min((int) ceil($distanceKm / 100), 15);

        return now()->addDays($days);
    }
}
