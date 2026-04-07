<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductCostCollector;
use App\Models\Manufacturing\ProductCostCollectorItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductCostCollectorService
{
    // ----------------------------------------------------------------
    // Collector lifecycle
    // ----------------------------------------------------------------

    /**
     * Get an existing open collector or create one for the given product/line/period.
     */
    public function getOrCreate(
        int $productId,
        ?int $productionLineId,
        int $period,
        int $year,
        int $orgId
    ): ProductCostCollector {
        return DB::transaction(function () use ($productId, $productionLineId, $period, $year, $orgId): ProductCostCollector {
            return ProductCostCollector::withoutGlobalScope('organization')
                ->firstOrCreate(
                    [
                        'organization_id'    => $orgId,
                        'product_id'         => $productId,
                        'production_line_id' => $productionLineId,
                        'period'             => $period,
                        'fiscal_year'        => $year,
                    ],
                    [
                        'status'                 => ProductCostCollector::STATUS_OPEN,
                        'standard_cost_total'    => 0,
                        'actual_cost_total'      => 0,
                        'total_variance'         => 0,
                        'quantity_produced'      => 0,
                        'cost_per_unit_standard' => 0,
                        'cost_per_unit_actual'   => 0,
                    ]
                );
        });
    }

    /**
     * Post (add or update) a cost item to the collector.
     *
     * @param array{cost_element_id?: int|null, cost_category: string, standard_cost: float, actual_cost: float} $costData
     */
    public function postCost(ProductCostCollector $collector, array $costData): void
    {
        DB::transaction(function () use ($collector, $costData): void {
            if ($collector->isClosed()) {
                throw new InvalidArgumentException('Cannot post cost to a closed collector.');
            }

            $existing = ProductCostCollectorItem::withoutGlobalScope('organization')
                ->where('product_cost_collector_id', $collector->id)
                ->where('cost_category', $costData['cost_category'])
                ->where('cost_element_id', $costData['cost_element_id'] ?? null)
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'standard_cost' => bcadd((string) $existing->standard_cost, (string) $costData['standard_cost'], 4),
                    'actual_cost'   => bcadd((string) $existing->actual_cost, (string) $costData['actual_cost'], 4),
                    'variance'      => bcsub(
                        bcadd((string) $existing->actual_cost, (string) $costData['actual_cost'], 4),
                        bcadd((string) $existing->standard_cost, (string) $costData['standard_cost'], 4),
                        4
                    ),
                ]);
            } else {
                $stdCost    = (float) ($costData['standard_cost'] ?? 0);
                $actualCost = (float) ($costData['actual_cost'] ?? 0);

                ProductCostCollectorItem::create([
                    'organization_id'           => $collector->organization_id,
                    'product_cost_collector_id' => $collector->id,
                    'cost_element_id'           => $costData['cost_element_id'] ?? null,
                    'cost_category'             => $costData['cost_category'],
                    'standard_cost'             => $stdCost,
                    'actual_cost'               => $actualCost,
                    'variance'                  => round($actualCost - $stdCost, 4),
                ]);
            }

            $this->recalculate($collector->fresh());
        });
    }

    /**
     * Recalculate collector totals from its items.
     */
    public function recalculate(ProductCostCollector $collector): void
    {
        $items = ProductCostCollectorItem::withoutGlobalScope('organization')
            ->where('product_cost_collector_id', $collector->id)
            ->get();

        $standardTotal = $items->sum(fn ($i) => (float) $i->standard_cost);
        $actualTotal   = $items->sum(fn ($i) => (float) $i->actual_cost);
        $variance      = round($actualTotal - $standardTotal, 4);

        $qty = (float) $collector->quantity_produced;

        $costPerUnitStd    = $qty > 0 ? round($standardTotal / $qty, 4) : 0.0;
        $costPerUnitActual = $qty > 0 ? round($actualTotal / $qty, 4) : 0.0;

        $collector->update([
            'standard_cost_total'    => round($standardTotal, 4),
            'actual_cost_total'      => round($actualTotal, 4),
            'total_variance'         => $variance,
            'cost_per_unit_standard' => $costPerUnitStd,
            'cost_per_unit_actual'   => $costPerUnitActual,
        ]);
    }

    /**
     * Close a collector — set status to closed, record closed_at.
     */
    public function close(ProductCostCollector $collector): ProductCostCollector
    {
        return DB::transaction(function () use ($collector): ProductCostCollector {
            if ($collector->isClosed()) {
                throw new InvalidArgumentException('Collector is already closed.');
            }

            $this->recalculate($collector->fresh());

            $collector->update([
                'status'    => ProductCostCollector::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            return $collector->fresh();
        });
    }

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProductCostCollector::with('product:id,name,sku')->orderBy('fiscal_year', 'desc')->orderBy('period', 'desc');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (!empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }
}
