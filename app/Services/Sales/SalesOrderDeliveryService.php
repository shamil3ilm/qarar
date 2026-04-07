<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Inventory\GoodsIssue;
use App\Models\Sales\SalesOrder;
use App\Services\Inventory\GoodsIssueService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesOrderDeliveryService
{
    public function __construct(
        private readonly GoodsIssueService $goodsIssueService
    ) {}

    /**
     * Create a Goods Issue (delivery goods issue) from a confirmed Sales Order.
     *
     * This implements the SAP SD flow:
     *   Sales Order (confirmed) → Goods Issue (sales_delivery) → Stock deducted → GL posted
     *
     * The caller can optionally specify which lines to include and target warehouse.
     * When $lineIds is null all open SO lines are included.
     *
     * @param  array<int>|null  $lineIds  Specific SO line IDs to include (null = all)
     */
    public function createDeliveryGoodsIssue(
        SalesOrder $order,
        int $warehouseId,
        int $userId,
        ?array $lineIds = null
    ): GoodsIssue {
        if (!in_array($order->status, [
            SalesOrder::STATUS_CONFIRMED,
            SalesOrder::STATUS_PROCESSING,
            SalesOrder::STATUS_PARTIALLY_DELIVERED,
        ], true)) {
            throw new InvalidArgumentException(
                "Sales order [{$order->order_number}] must be confirmed before a delivery can be created."
            );
        }

        return DB::transaction(function () use ($order, $warehouseId, $userId, $lineIds): GoodsIssue {
            $lines = $order->lines()
                ->with('product')
                ->when($lineIds !== null, fn ($q) => $q->whereIn('id', $lineIds))
                ->whereNotNull('product_id')
                ->get();

            if ($lines->isEmpty()) {
                throw new InvalidArgumentException('No deliverable lines found on the sales order.');
            }

            $giLines = $lines->map(fn ($line) => [
                'product_id'  => $line->product_id,
                'variant_id'  => $line->variant_id ?? null,
                'quantity'    => (float) $line->quantity,
                'unit_id'     => $line->unit_id ?? null,
                'reason_code' => 'sales_delivery',
                'notes'       => "SO {$order->order_number} line {$line->id}",
            ])->toArray();

            $gi = $this->goodsIssueService->create([
                'organization_id' => $order->organization_id,
                'warehouse_id'    => $warehouseId,
                'movement_type'   => GoodsIssue::MOVEMENT_SALES_DELIVERY,
                'reference_type'  => 'sales_order',
                'reference_id'    => $order->id,
                'issue_date'      => now()->toDateString(),
                'notes'           => "Delivery for sales order {$order->order_number}",
                'lines'           => $giLines,
            ], $userId);

            // Auto-post the goods issue
            $this->goodsIssueService->post($gi, $userId);

            // Update order status
            $this->updateOrderDeliveryStatus($order);

            return $gi->fresh(['lines.product', 'warehouse']);
        });
    }

    /**
     * Update sales order delivery status based on current GI count.
     */
    private function updateOrderDeliveryStatus(SalesOrder $order): void
    {
        if (!in_array($order->status, [
            SalesOrder::STATUS_CONFIRMED,
            SalesOrder::STATUS_PROCESSING,
            SalesOrder::STATUS_PARTIALLY_DELIVERED,
        ], true)) {
            return;
        }

        $postedGiCount = GoodsIssue::where('reference_type', 'sales_order')
            ->where('reference_id', $order->id)
            ->where('status', GoodsIssue::STATUS_POSTED)
            ->count();

        if ($postedGiCount > 0) {
            $newStatus = $postedGiCount >= 1
                ? SalesOrder::STATUS_PARTIALLY_DELIVERED
                : SalesOrder::STATUS_PROCESSING;

            if ($order->status !== SalesOrder::STATUS_PARTIALLY_DELIVERED) {
                $order->update(['status' => $newStatus]);
            }
        }
    }
}
