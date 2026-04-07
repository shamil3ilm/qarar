<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\SalesOrderCostEstimate;
use App\Models\Sales\SalesOrderCostEstimateItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesOrderCostingService
{
    // ----------------------------------------------------------------
    // Estimate CRUD
    // ----------------------------------------------------------------

    public function createEstimate(array $data): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($data): SalesOrderCostEstimate {
            return SalesOrderCostEstimate::create(array_merge($data, [
                'status'    => SalesOrderCostEstimate::STATUS_DRAFT,
                'costed_at' => now(),
            ]));
        });
    }

    public function update(SalesOrderCostEstimate $estimate, array $data): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($estimate, $data): SalesOrderCostEstimate {
            if ($estimate->isReleased()) {
                throw new InvalidArgumentException('Cannot modify a released cost estimate.');
            }

            $estimate->update($data);

            return $estimate->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Items
    // ----------------------------------------------------------------

    public function addItem(SalesOrderCostEstimate $estimate, array $data): SalesOrderCostEstimateItem
    {
        return DB::transaction(function () use ($estimate, $data): SalesOrderCostEstimateItem {
            if ($estimate->isReleased()) {
                throw new InvalidArgumentException('Cannot add items to a released cost estimate.');
            }

            $quantity    = (float) $data['quantity'];
            $costPerUnit = (float) $data['cost_per_unit'];
            $totalCost   = round($quantity * $costPerUnit, 4);

            $item = SalesOrderCostEstimateItem::create(array_merge($data, [
                'organization_id'              => $estimate->organization_id,
                'sales_order_cost_estimate_id' => $estimate->id,
                'total_cost'                   => $totalCost,
                'revenue'                      => $data['revenue'] ?? 0,
            ]));

            $this->recalculate($estimate->fresh());

            return $item->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Recalculation
    // ----------------------------------------------------------------

    public function recalculate(SalesOrderCostEstimate $estimate): void
    {
        $items = SalesOrderCostEstimateItem::withoutGlobalScope('organization')
            ->where('sales_order_cost_estimate_id', $estimate->id)
            ->get();

        $totalCost    = round($items->sum(fn ($i) => (float) $i->total_cost), 4);
        $totalRevenue = round($items->sum(fn ($i) => (float) $i->revenue), 4);
        $grossMargin  = round($totalRevenue - $totalCost, 4);
        $marginPct    = $totalRevenue > 0
            ? round(($grossMargin / $totalRevenue) * 100, 4)
            : 0.0;

        $estimate->update([
            'total_cost'           => $totalCost,
            'total_revenue'        => $totalRevenue,
            'gross_margin'         => $grossMargin,
            'gross_margin_percent' => $marginPct,
        ]);
    }

    // ----------------------------------------------------------------
    // Release
    // ----------------------------------------------------------------

    public function release(SalesOrderCostEstimate $estimate): SalesOrderCostEstimate
    {
        return DB::transaction(function () use ($estimate): SalesOrderCostEstimate {
            if (!$estimate->isDraft()) {
                throw new InvalidArgumentException('Only draft estimates can be released.');
            }

            $this->recalculate($estimate->fresh());

            $estimate->update(['status' => SalesOrderCostEstimate::STATUS_RELEASED]);

            return $estimate->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Listing
    // ----------------------------------------------------------------

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SalesOrderCostEstimate::with(['salesOrder', 'costedBy:id,name'])
            ->orderBy('id', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        if (!empty($filters['quotation_id'])) {
            $query->where('quotation_id', $filters['quotation_id']);
        }

        return $query->paginate($perPage);
    }
}
