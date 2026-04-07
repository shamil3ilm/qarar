<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProcurementInspection;
use App\Models\Manufacturing\ProcurementInspectionConfig;
use App\Models\Manufacturing\ProcurementInspectionResult;
use Illuminate\Support\Facades\DB;

class ProcurementInspectionService
{
    /**
     * Determine whether inspection is required for a product/vendor combination.
     */
    public function shouldInspect(int $productId, int $vendorId): ?ProcurementInspectionConfig
    {
        $orgId = auth()->user()->organization_id;

        // Exact match: product + vendor
        $config = ProcurementInspectionConfig::where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->where('inspection_required', true)
            ->first();

        if ($config !== null) {
            return $config;
        }

        // Fallback: product only (any vendor)
        $config = ProcurementInspectionConfig::where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->where('inspection_required', true)
            ->first();

        if ($config !== null) {
            return $config;
        }

        // Fallback: vendor only (any product)
        return ProcurementInspectionConfig::where('organization_id', $orgId)
            ->whereNull('product_id')
            ->where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->where('inspection_required', true)
            ->first();
    }

    /**
     * Create a procurement inspection record triggered by a goods receipt.
     */
    public function createInspectionOnGoodsReceipt(int $goodsReceiptId): ?ProcurementInspection
    {
        // This is a stub integration point; callers pass line data directly.
        // Full implementation would query the GoodsReceipt model for product/vendor/quantity.
        return null;
    }

    /**
     * Create a procurement inspection manually (or from a goods receipt event).
     */
    public function createInspection(array $data): ProcurementInspection
    {
        return DB::transaction(function () use ($data): ProcurementInspection {
            $orgId    = auth()->user()->organization_id;
            $config   = $data['config'] ?? $this->shouldInspect(
                $data['product_id'],
                $data['vendor_id'] ?? 0,
            );

            $qtyReceived  = (float) $data['quantity_received'];
            $qtyToInspect = $config !== null
                ? $config->calculateQuantityToInspect($qtyReceived)
                : $qtyReceived;

            return ProcurementInspection::create([
                'organization_id'    => $orgId,
                'purchase_order_id'  => $data['purchase_order_id'] ?? null,
                'goods_receipt_id'   => $data['goods_receipt_id'] ?? null,
                'product_id'         => $data['product_id'],
                'vendor_id'          => $data['vendor_id'] ?? null,
                'inspection_lot_id'  => $data['inspection_lot_id'] ?? null,
                'quantity_received'  => $qtyReceived,
                'quantity_to_inspect' => $qtyToInspect,
                'quantity_inspected' => 0,
                'quantity_accepted'  => 0,
                'quantity_rejected'  => 0,
                'status'             => ProcurementInspection::STATUS_PENDING,
                'notes'              => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Record inspection characteristic results against an inspection.
     */
    public function recordResults(ProcurementInspection $inspection, array $results): void
    {
        DB::transaction(function () use ($inspection, $results): void {
            foreach ($results['characteristics'] ?? [] as $char) {
                ProcurementInspectionResult::create([
                    'procurement_inspection_id' => $inspection->id,
                    'characteristic_name'       => $char['characteristic_name'],
                    'specification_min'          => $char['specification_min'] ?? null,
                    'specification_max'          => $char['specification_max'] ?? null,
                    'actual_value'              => $char['actual_value'] ?? null,
                    'is_within_spec'            => $char['is_within_spec'] ?? null,
                    'defect_description'        => $char['defect_description'] ?? null,
                ]);
            }

            $qtyAccepted = (float) ($results['quantity_accepted'] ?? $inspection->quantity_accepted);
            $qtyRejected = (float) ($results['quantity_rejected'] ?? $inspection->quantity_rejected);
            $qtyInspected = $qtyAccepted + $qtyRejected;

            $defectRate = $qtyInspected > 0
                ? round($qtyRejected / $qtyInspected * 100, 2)
                : 0.0;

            $inspection->update([
                'quantity_inspected' => $qtyInspected,
                'quantity_accepted'  => $qtyAccepted,
                'quantity_rejected'  => $qtyRejected,
                'defect_rate'        => $defectRate,
                'status'             => ProcurementInspection::STATUS_COMPLETED,
                'inspection_date'    => now(),
                'inspected_by'       => $results['inspected_by'] ?? auth()->id(),
            ]);
        });
    }

    /**
     * Approve a completed inspection.
     */
    public function approveInspection(ProcurementInspection $inspection): void
    {
        $inspection->update([
            'status' => ProcurementInspection::STATUS_APPROVED,
        ]);
    }

    /**
     * Reject a completed inspection with a reason.
     */
    public function rejectInspection(ProcurementInspection $inspection, string $reason): void
    {
        $existing = $inspection->notes ?? '';
        $notes    = trim($existing . "\nRejection reason: " . $reason);

        $inspection->update([
            'status' => ProcurementInspection::STATUS_REJECTED,
            'notes'  => $notes,
        ]);
    }

    /**
     * Calculate a vendor's quality score based on past inspections.
     *
     * @return array{avg_defect_rate: float, total_inspections: int, pass_rate: float}
     */
    public function getVendorQualityScore(int $vendorId): array
    {
        $orgId = auth()->user()->organization_id;

        $inspections = ProcurementInspection::where('organization_id', $orgId)
            ->where('vendor_id', $vendorId)
            ->whereIn('status', [
                ProcurementInspection::STATUS_COMPLETED,
                ProcurementInspection::STATUS_APPROVED,
                ProcurementInspection::STATUS_REJECTED,
            ])
            ->get();

        $total = $inspections->count();

        if ($total === 0) {
            return [
                'avg_defect_rate'  => 0.0,
                'total_inspections' => 0,
                'pass_rate'        => 0.0,
            ];
        }

        $avgDefectRate = $inspections->avg('defect_rate') ?? 0.0;
        $approved      = $inspections->whereIn('status', [
            ProcurementInspection::STATUS_APPROVED,
            ProcurementInspection::STATUS_COMPLETED,
        ])->count();
        $passRate      = round(($approved / $total) * 100, 2);

        return [
            'avg_defect_rate'  => round((float) $avgDefectRate, 2),
            'total_inspections' => $total,
            'pass_rate'        => $passRate,
        ];
    }
}
