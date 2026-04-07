<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PoWbsCommitment;
use App\Models\Purchase\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WbsCommitmentService
{
    /**
     * Create a WBS commitment from a purchase order line.
     * Returns null if the line has no wbs_element_id assigned.
     */
    public function createCommitment(PurchaseOrderLine $line): ?PoWbsCommitment
    {
        if (empty($line->wbs_element_id)) {
            return null;
        }

        return DB::transaction(function () use ($line): PoWbsCommitment {
            // Close any existing open commitment for this line before creating a new one
            PoWbsCommitment::where('purchase_order_line_id', $line->id)
                ->where('status', PoWbsCommitment::STATUS_OPEN)
                ->update(['status' => PoWbsCommitment::STATUS_CLOSED]);

            return PoWbsCommitment::create([
                'organization_id'        => $line->purchaseOrder->organization_id,
                'purchase_order_id'      => $line->purchase_order_id,
                'purchase_order_line_id' => $line->id,
                'wbs_element_id'         => $line->wbs_element_id,
                'committed_amount'       => $line->total,
                'currency_code'          => $line->purchaseOrder->currency_code ?? 'SAR',
                'commitment_date'        => now()->toDateString(),
                'status'                 => PoWbsCommitment::STATUS_OPEN,
            ]);
        });
    }

    /**
     * Update a commitment's status when goods are received against a PO line.
     */
    public function updateCommitmentOnGoodsReceipt(int $poLineId, float $deliveredQty): void
    {
        $commitment = PoWbsCommitment::where('purchase_order_line_id', $poLineId)
            ->whereIn('status', [PoWbsCommitment::STATUS_OPEN, PoWbsCommitment::STATUS_PARTIALLY_DELIVERED])
            ->latest()
            ->first();

        if ($commitment === null) {
            return;
        }

        $line = PurchaseOrderLine::findOrFail($poLineId);
        $totalQty = (float) $line->quantity;

        if ($totalQty <= 0) {
            return;
        }

        $isFullyDelivered = bccomp((string) $deliveredQty, (string) $totalQty, 4) >= 0;

        $commitment->update([
            'status' => $isFullyDelivered
                ? PoWbsCommitment::STATUS_CLOSED
                : PoWbsCommitment::STATUS_PARTIALLY_DELIVERED,
        ]);
    }

    /**
     * Explicitly close a commitment by PO line ID.
     */
    public function closeCommitment(int $poLineId): void
    {
        PoWbsCommitment::where('purchase_order_line_id', $poLineId)
            ->whereNot('status', PoWbsCommitment::STATUS_CLOSED)
            ->update(['status' => PoWbsCommitment::STATUS_CLOSED]);
    }

    /**
     * Get all commitments for a WBS element.
     */
    public function getCommitmentsForWbs(int $wbsElementId): Collection
    {
        return PoWbsCommitment::where('wbs_element_id', $wbsElementId)
            ->with(['purchaseOrder', 'purchaseOrderLine.product'])
            ->orderByDesc('commitment_date')
            ->get();
    }

    /**
     * Get budget vs commitment summary for a WBS element.
     *
     * Returns:
     *   budget        - planned cost from WBS element
     *   committed     - sum of open/partial commitments
     *   actual        - sum of closed commitments (delivered)
     *   available     - budget minus committed and actual
     */
    public function getBudgetVsCommitment(int $wbsElementId): array
    {
        $wbsElement = \App\Models\Projects\WbsElement::find($wbsElementId);

        if ($wbsElement === null) {
            throw new RuntimeException("WBS element #{$wbsElementId} not found.");
        }

        $committed = (float) PoWbsCommitment::where('wbs_element_id', $wbsElementId)
            ->whereIn('status', [PoWbsCommitment::STATUS_OPEN, PoWbsCommitment::STATUS_PARTIALLY_DELIVERED])
            ->sum('committed_amount');

        $actual = (float) PoWbsCommitment::where('wbs_element_id', $wbsElementId)
            ->where('status', PoWbsCommitment::STATUS_CLOSED)
            ->sum('committed_amount');

        $budget = (float) $wbsElement->planned_cost;
        $available = (float) bcsub(
            (string) $budget,
            (string) bcadd((string) $committed, (string) $actual, 4),
            4
        );

        return [
            'wbs_element_id'   => $wbsElementId,
            'wbs_code'         => $wbsElement->wbs_code,
            'wbs_name'         => $wbsElement->name,
            'budget'           => $budget,
            'committed'        => $committed,
            'actual'           => $actual,
            'available'        => $available,
            'utilization_pct'  => $budget > 0
                ? round((($committed + $actual) / $budget) * 100, 2)
                : 0,
        ];
    }
}
