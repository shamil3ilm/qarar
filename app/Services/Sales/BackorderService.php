<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\BackorderRecord;
use App\Services\Inventory\StockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BackorderService
{
    public function __construct(
        private StockService $stockService,
    ) {}

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = BackorderRecord::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query->with(['salesOrder', 'product', 'salesOrderLine'])
            ->byPriority()
            ->paginate($perPage);
    }

    public function create(array $data): BackorderRecord
    {
        return BackorderRecord::create($data);
    }

    public function reschedule(BackorderRecord $record, string $newDate, ?string $reason = null): BackorderRecord
    {
        $updateData = ['rescheduled_delivery_date' => $newDate];

        if ($reason !== null) {
            $updateData['reason'] = $reason;
        }

        $record->update($updateData);
        return $record->fresh();
    }

    public function fulfill(BackorderRecord $record, float $quantity): BackorderRecord
    {
        return DB::transaction(function () use ($record, $quantity): BackorderRecord {
            $newFulfilled = (float) $record->fulfilled_quantity + $quantity;
            $backordered = (float) $record->backordered_quantity;

            $newFulfilled = min($newFulfilled, $backordered);

            $status = $newFulfilled >= $backordered
                ? BackorderRecord::STATUS_FULFILLED
                : BackorderRecord::STATUS_PARTIALLY_FULFILLED;

            $record->update([
                'fulfilled_quantity' => $newFulfilled,
                'status' => $status,
            ]);

            return $record->fresh();
        });
    }

    public function autoFulfillFromStock(int $productId): array
    {
        $results = [];

        $openBackorders = BackorderRecord::forProduct($productId)
            ->open()
            ->byPriority()
            ->get();

        if ($openBackorders->isEmpty()) {
            return $results;
        }

        // Get available stock across all warehouses for this product
        $availableStock = $this->stockService->getAvailableStock($productId);

        foreach ($openBackorders as $backorder) {
            if ($availableStock <= 0) {
                break;
            }

            $remaining = $backorder->getRemainingQuantity();
            $toFulfill = min($remaining, $availableStock);

            if ($toFulfill > 0) {
                $this->fulfill($backorder, $toFulfill);
                $availableStock -= $toFulfill;
                $results[] = [
                    'backorder_id' => $backorder->id,
                    'quantity_fulfilled' => $toFulfill,
                    'status' => $backorder->fresh()->status,
                ];
            }
        }

        return $results;
    }

    public function cancel(BackorderRecord $record): BackorderRecord
    {
        $record->update(['status' => BackorderRecord::STATUS_CANCELLED]);
        return $record->fresh();
    }

    public function getBackorderReport(array $filters): array
    {
        $query = BackorderRecord::query()
            ->whereIn('status', [BackorderRecord::STATUS_OPEN, BackorderRecord::STATUS_PARTIALLY_FULFILLED]);

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $byProduct = (clone $query)
            ->selectRaw('product_id, sum(backordered_quantity) as total_backordered, sum(fulfilled_quantity) as total_fulfilled, count(*) as record_count')
            ->groupBy('product_id')
            ->with('product:id,name,sku')
            ->get();

        $bySalesOrder = (clone $query)
            ->selectRaw('sales_order_id, count(*) as backorder_count, sum(backordered_quantity - fulfilled_quantity) as outstanding_quantity')
            ->groupBy('sales_order_id')
            ->with('salesOrder:id,order_number')
            ->get();

        return [
            'by_product' => $byProduct,
            'by_sales_order' => $bySalesOrder,
            'summary' => [
                'total_open' => BackorderRecord::where('status', BackorderRecord::STATUS_OPEN)->count(),
                'total_partially_fulfilled' => BackorderRecord::where('status', BackorderRecord::STATUS_PARTIALLY_FULFILLED)->count(),
            ],
        ];
    }
}
