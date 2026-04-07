<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    protected int $organizationId;
    protected ?int $branchId = null;

    public function setContext(int $organizationId, ?int $branchId = null): self
    {
        $this->organizationId = $organizationId;
        $this->branchId = $branchId;
        return $this;
    }

    /**
     * Generate Stock Valuation Report.
     */
    public function generateStockValuation(
        ?int $warehouseId = null,
        ?int $categoryId = null,
        ?string $valuationMethod = null
    ): array {
        $query = DB::table('stock_levels as sl')
            ->join('products as p', 'sl.product_id', '=', 'p.id')
            ->join('warehouses as w', 'sl.warehouse_id', '=', 'w.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('units_of_measure as u', 'p.unit_id', '=', 'u.id')
            ->where('sl.organization_id', $this->organizationId)
            ->where('p.type', 'goods') // Only physical inventory
            ->where('sl.quantity', '>', 0);

        if ($warehouseId) {
            $query->where('sl.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('p.category_id', $categoryId);
        }

        $stocks = $query->select([
            'p.id as product_id',
            'p.sku',
            'p.name as product_name',
            'p.costing_method',
            'c.name as category_name',
            'w.id as warehouse_id',
            'w.name as warehouse_name',
            'u.symbol as unit',
            'sl.quantity',
            'sl.reserved_quantity',
            'sl.average_cost',
            'sl.last_purchase_price',
        ])
            ->orderBy('c.name')
            ->orderBy('p.name')
            ->get();

        $items = [];
        $totalValue = '0';
        $totalQuantity = '0';
        $categoryTotals = [];

        foreach ($stocks as $stock) {
            $method = $valuationMethod ?? $stock->costing_method ?? 'weighted_average';
            $unitCost = $this->getUnitCost($stock, $method);
            $totalCost = bcmul((string) $stock->quantity, (string) $unitCost, 4);
            $availableQty = bcsub((string) $stock->quantity, (string) ($stock->reserved_quantity ?? 0), 4);

            $items[] = [
                'product_id' => $stock->product_id,
                'sku' => $stock->sku,
                'product_name' => $stock->product_name,
                'category' => $stock->category_name ?? 'Uncategorized',
                'warehouse_id' => $stock->warehouse_id,
                'warehouse' => $stock->warehouse_name,
                'unit' => $stock->unit,
                'quantity' => (float) $stock->quantity,
                'reserved' => (float) ($stock->reserved_quantity ?? 0),
                'available' => (float) $availableQty,
                'unit_cost' => (float) $unitCost,
                'total_value' => (float) $totalCost,
                'valuation_method' => $method,
            ];

            $totalValue = bcadd($totalValue, $totalCost, 4);
            $totalQuantity = bcadd($totalQuantity, (string) $stock->quantity, 4);

            $category = $stock->category_name ?? 'Uncategorized';
            if (!isset($categoryTotals[$category])) {
                $categoryTotals[$category] = ['quantity' => '0', 'value' => '0'];
            }
            $categoryTotals[$category]['quantity'] = bcadd($categoryTotals[$category]['quantity'], (string) $stock->quantity, 4);
            $categoryTotals[$category]['value'] = bcadd($categoryTotals[$category]['value'], $totalCost, 4);
        }

        // Get warehouse breakdown
        $warehouseTotals = collect($items)->groupBy('warehouse')->map(function ($items) {
            return [
                'quantity' => $items->sum('quantity'),
                'value' => $items->sum('total_value'),
            ];
        })->toArray();

        $itemCount = count($items);

        return [
            'report_type' => 'stock_valuation',
            'as_of_date' => now()->toDateString(),
            'filters' => [
                'warehouse_id' => $warehouseId,
                'category_id' => $categoryId,
                'valuation_method' => $valuationMethod,
            ],
            'items' => $items,
            'summary' => [
                'total_quantity' => (float) $totalQuantity,
                'total_value' => (float) $totalValue,
                'product_count' => $itemCount,
                'average_item_value' => $itemCount > 0
                    ? (float) bcdiv($totalValue, (string) $itemCount, 4)
                    : 0,
            ],
            'by_category' => $categoryTotals,
            'by_warehouse' => $warehouseTotals,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Stock Movement Report.
     */
    public function generateStockMovement(
        string $startDate,
        string $endDate,
        ?int $productId = null,
        ?int $warehouseId = null,
        ?string $movementType = null
    ): array {
        $base = DB::table('stock_movements as sm')
            ->where('sm.organization_id', $this->organizationId)
            ->whereBetween('sm.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($productId) {
            $base->where('sm.product_id', $productId);
        }
        if ($warehouseId) {
            $base->where('sm.warehouse_id', $warehouseId);
        }
        if ($movementType) {
            $base->where('sm.movement_type', $movementType);
        }

        $inTypes  = ['in', 'purchase', 'return_in', 'adjustment_in', 'transfer_in'];
        $outTypes = ['out', 'sale', 'return_out', 'adjustment_out', 'transfer_out'];
        $inList   = implode("','", $inTypes);
        $outList  = implode("','", $outTypes);

        // --- Summary via DB CASE SUM (no collection load) ---
        $summaryRow = (clone $base)->selectRaw("
            SUM(CASE WHEN movement_type IN ('{$inList}') THEN quantity  ELSE 0 END) as total_in,
            SUM(CASE WHEN movement_type IN ('{$inList}') THEN total_cost ELSE 0 END) as total_in_value,
            SUM(CASE WHEN movement_type IN ('{$outList}') THEN quantity  ELSE 0 END) as total_out,
            SUM(CASE WHEN movement_type IN ('{$outList}') THEN total_cost ELSE 0 END) as total_out_value,
            COUNT(*) as movement_count
        ")->first();

        $summary = [
            'total_in'       => (float) ($summaryRow->total_in ?? 0),
            'total_in_value' => (float) ($summaryRow->total_in_value ?? 0),
            'total_out'      => (float) ($summaryRow->total_out ?? 0),
            'total_out_value'=> (float) ($summaryRow->total_out_value ?? 0),
            'net_movement'   => (float) (($summaryRow->total_in ?? 0) - ($summaryRow->total_out ?? 0)),
            'movement_count' => (int) ($summaryRow->movement_count ?? 0),
        ];

        // --- By movement type (DB GROUP BY) ---
        $byTypeRows = (clone $base)
            ->selectRaw('sm.movement_type as type, COUNT(*) as cnt, SUM(sm.quantity) as qty, SUM(sm.total_cost) as val')
            ->groupBy('sm.movement_type')
            ->orderByDesc('cnt')
            ->get();

        $byType = $byTypeRows->map(fn($r) => [
            'type'     => $r->type,
            'label'    => $this->getMovementTypeLabel($r->type),
            'count'    => (int) $r->cnt,
            'quantity' => (float) $r->qty,
            'value'    => (float) $r->val,
        ])->values()->toArray();

        // --- By product (DB GROUP BY, top 20) ---
        $byProductRows = (clone $base)
            ->join('products as p', 'sm.product_id', '=', 'p.id')
            ->selectRaw("
                sm.product_id,
                p.sku,
                p.name as product_name,
                COUNT(*) as movement_count,
                SUM(CASE WHEN sm.movement_type IN ('{$inList}')  THEN sm.quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN sm.movement_type IN ('{$outList}') THEN sm.quantity ELSE 0 END) as total_out
            ")
            ->groupBy('sm.product_id', 'p.sku', 'p.name')
            ->orderByDesc('movement_count')
            ->limit(20)
            ->get();

        $byProduct = $byProductRows->map(fn($r) => [
            'product_id'     => $r->product_id,
            'sku'            => $r->sku,
            'product_name'   => $r->product_name,
            'movement_count' => (int) $r->movement_count,
            'total_in'       => (float) $r->total_in,
            'total_out'      => (float) $r->total_out,
            'net'            => (float) ($r->total_in - $r->total_out),
        ])->values()->toArray();

        // --- Detail rows (capped at 500) ---
        $detailRows = (clone $base)
            ->join('products as p', 'sm.product_id', '=', 'p.id')
            ->join('warehouses as w', 'sm.warehouse_id', '=', 'w.id')
            ->leftJoin('units_of_measure as u', 'p.unit_id', '=', 'u.id')
            ->leftJoin('users as usr', 'sm.created_by', '=', 'usr.id')
            ->select([
                'sm.id',
                'sm.created_at as movement_date',
                'sm.movement_type',
                'sm.quantity',
                'sm.unit_cost',
                'sm.total_cost',
                'sm.reference_type',
                'sm.reference_id',
                'sm.notes',
                'p.id as product_id',
                'p.sku',
                'p.name as product_name',
                'w.name as warehouse_name',
                'u.symbol as unit',
                'usr.name as created_by',
            ])
            ->orderBy('sm.created_at', 'desc')
            ->limit(500)
            ->get();

        $movements = $detailRows->map(fn($m) => [
            'id'           => $m->id,
            'date'         => $m->movement_date,
            'type'         => $m->movement_type,
            'type_label'   => $this->getMovementTypeLabel($m->movement_type),
            'product_id'   => $m->product_id,
            'sku'          => $m->sku,
            'product_name' => $m->product_name,
            'warehouse'    => $m->warehouse_name,
            'quantity'     => (float) $m->quantity,
            'unit'         => $m->unit,
            'unit_cost'    => (float) $m->unit_cost,
            'total_cost'   => (float) $m->total_cost,
            'reference'    => $m->reference_type ? "{$m->reference_type}#{$m->reference_id}" : null,
            'notes'        => $m->notes,
            'created_by'   => $m->created_by,
        ])->values()->toArray();

        return [
            'report_type' => 'stock_movement',
            'period_start' => $startDate,
            'period_end'   => $endDate,
            'filters'      => [
                'product_id'    => $productId,
                'warehouse_id'  => $warehouseId,
                'movement_type' => $movementType,
            ],
            'movements'     => $movements,
            'summary'       => $summary,
            'by_type'       => $byType,
            'by_product'    => $byProduct,
            'generated_at'  => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Low Stock Alert Report.
     */
    public function generateLowStockReport(?int $warehouseId = null): array
    {
        $query = DB::table('stock_levels as sl')
            ->join('products as p', 'sl.product_id', '=', 'p.id')
            ->join('warehouses as w', 'sl.warehouse_id', '=', 'w.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('units_of_measure as u', 'p.unit_id', '=', 'u.id')
            ->where('sl.organization_id', $this->organizationId)
            ->where('p.type', 'goods')
            ->where('p.is_active', true)
            ->whereColumn('sl.quantity', '<=', 'sl.reorder_level')
            ->where('sl.reorder_level', '>', 0);

        if ($warehouseId) {
            $query->where('sl.warehouse_id', $warehouseId);
        }

        $items = $query->select([
            'p.id as product_id',
            'p.sku',
            'p.name as product_name',
            'c.name as category_name',
            'w.id as warehouse_id',
            'w.name as warehouse_name',
            'u.symbol as unit',
            'sl.quantity',
            'sl.reserved_quantity',
            'sl.reorder_level',
            'sl.reorder_quantity',
            'sl.average_cost',
        ])
            ->orderByRaw('(sl.reorder_level - sl.quantity) DESC')
            ->get();

        $criticalItems = [];
        $lowItems = [];
        $totalReorderValue = '0';

        foreach ($items as $item) {
            $availableQty = bcsub((string) $item->quantity, (string) ($item->reserved_quantity ?? 0), 4);
            $shortfall = bcsub((string) $item->reorder_level, (string) $item->quantity, 4);
            $reorderQty = (string) ($item->reorder_quantity ?? $shortfall);
            $suggestedOrder = bccomp($reorderQty, $shortfall, 4) >= 0 ? $reorderQty : $shortfall;
            $orderValue = bcmul($suggestedOrder, (string) ($item->average_cost ?? 0), 4);

            $stockPct = bccomp((string) $item->reorder_level, '0', 4) > 0
                ? (float) bcdiv(bcmul((string) $item->quantity, '100', 4), (string) $item->reorder_level, 4)
                : 0.0;

            $entry = [
                'product_id' => $item->product_id,
                'sku' => $item->sku,
                'product_name' => $item->product_name,
                'category' => $item->category_name ?? 'Uncategorized',
                'warehouse_id' => $item->warehouse_id,
                'warehouse' => $item->warehouse_name,
                'unit' => $item->unit,
                'current_stock' => (float) $item->quantity,
                'available' => (float) $availableQty,
                'reorder_level' => (float) $item->reorder_level,
                'shortfall' => (float) $shortfall,
                'suggested_order' => (float) $suggestedOrder,
                'estimated_cost' => (float) $orderValue,
                'stock_percentage' => round($stockPct, 1),
            ];

            // Critical if stock is below 25% of reorder level or zero
            $stockRatio = bccomp((string) $item->reorder_level, '0', 4) > 0
                ? (float) bcdiv((string) $item->quantity, (string) $item->reorder_level, 4)
                : 1.0;
            if (bccomp((string) $item->quantity, '0', 4) <= 0 || $stockRatio < 0.25) {
                $entry['severity'] = 'critical';
                $criticalItems[] = $entry;
            } else {
                $entry['severity'] = 'low';
                $lowItems[] = $entry;
            }

            $totalReorderValue = bcadd($totalReorderValue, $orderValue, 4);
        }

        return [
            'report_type' => 'low_stock',
            'as_of_date' => now()->toDateString(),
            'filters' => [
                'warehouse_id' => $warehouseId,
            ],
            'critical_items' => $criticalItems,
            'low_items' => $lowItems,
            'summary' => [
                'critical_count' => count($criticalItems),
                'low_count' => count($lowItems),
                'total_items' => count($items),
                'total_reorder_value' => (float) $totalReorderValue,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Inventory Turnover Report.
     */
    public function generateInventoryTurnover(string $startDate, string $endDate): array
    {
        // Get average inventory value for the period
        $avgInventory = DB::table('stock_levels')
            ->where('organization_id', $this->organizationId)
            ->selectRaw('AVG(quantity * average_cost) as avg_value')
            ->value('avg_value') ?? 0;

        // Get COGS for the period
        $cogs = DB::table('document_lines as dl')
            ->join('invoices as i', function ($join) {
                $join->on('dl.document_id', '=', 'i.id')
                    ->where('dl.document_type', '=', 'invoice');
            })
            ->where('i.organization_id', $this->organizationId)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$startDate, $endDate])
            ->join('products as p', 'dl.product_id', '=', 'p.id')
            ->where('p.type', 'goods')
            ->selectRaw('SUM(dl.quantity * COALESCE(p.purchase_price, 0)) as cogs')
            ->value('cogs') ?? 0;

        // Calculate turnover metrics
        $periodDays = \Carbon\Carbon::parse($startDate)->diffInDays($endDate) + 1;
        $turnoverRatio = bccomp((string) $avgInventory, '0', 4) > 0
            ? bcdiv((string) $cogs, (string) $avgInventory, 8)
            : '0';
        $daysInInventory = bccomp($turnoverRatio, '0', 8) > 0
            ? bcdiv((string) $periodDays, $turnoverRatio, 4)
            : '0';

        // Get turnover by category
        $byCategory = DB::table('document_lines as dl')
            ->join('invoices as i', function ($join) {
                $join->on('dl.document_id', '=', 'i.id')
                    ->where('dl.document_type', '=', 'invoice');
            })
            ->join('products as p', 'dl.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('i.organization_id', $this->organizationId)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$startDate, $endDate])
            ->where('p.type', 'goods')
            ->groupBy('c.id', 'c.name')
            ->selectRaw('c.name as category, SUM(dl.quantity) as units_sold, SUM(dl.total) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        return [
            'report_type' => 'inventory_turnover',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'period_days' => $periodDays,
            'metrics' => [
                'average_inventory_value' => (float) $avgInventory,
                'cost_of_goods_sold' => (float) $cogs,
                'turnover_ratio' => round((float) $turnoverRatio, 2),
                'days_in_inventory' => round((float) $daysInInventory, 1),
                'annual_turnover' => round(
                    (float) bcmul($turnoverRatio, bcdiv('365', (string) $periodDays, 8), 4),
                    2
                ),
            ],
            'by_category' => $byCategory,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Batch/Expiry Report.
     */
    public function generateExpiryReport(int $daysAhead = 90, ?int $warehouseId = null): array
    {
        $today = now()->toDateString();
        $futureDate = now()->addDays($daysAhead)->toDateString();

        $query = DB::table('inventory_batches as pb')
            ->join('products as p', 'pb.product_id', '=', 'p.id')
            ->join('warehouses as w', 'pb.warehouse_id', '=', 'w.id')
            ->leftJoin('units_of_measure as u', 'p.unit_id', '=', 'u.id')
            ->where('pb.organization_id', $this->organizationId)
            ->where('pb.quantity', '>', 0)
            ->whereNotNull('pb.expiry_date')
            ->where('pb.expiry_date', '<=', $futureDate)
            ->orderBy('pb.expiry_date');

        if ($warehouseId) {
            $query->where('pb.warehouse_id', $warehouseId);
        }

        $batches = $query->select([
            'pb.id',
            'pb.batch_number',
            'pb.expiry_date',
            'pb.quantity',
            'pb.unit_cost',
            'p.id as product_id',
            'p.sku',
            'p.name as product_name',
            'w.name as warehouse_name',
            'u.symbol as unit',
        ])->get();

        $expired = [];
        $expiringSoon = [];
        $expiringLater = [];

        foreach ($batches as $batch) {
            $expiryDate = \Carbon\Carbon::parse($batch->expiry_date);
            $daysUntilExpiry = now()->startOfDay()->diffInDays($expiryDate, false);
            $value = bcmul((string) $batch->quantity, (string) ($batch->unit_cost ?? 0), 4);

            $entry = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'product_id' => $batch->product_id,
                'sku' => $batch->sku,
                'product_name' => $batch->product_name,
                'warehouse' => $batch->warehouse_name,
                'quantity' => (float) $batch->quantity,
                'unit' => $batch->unit,
                'unit_cost' => (float) ($batch->unit_cost ?? 0),
                'total_value' => (float) $value,
                'expiry_date' => $batch->expiry_date,
                'days_until_expiry' => $daysUntilExpiry,
            ];

            if ($daysUntilExpiry < 0) {
                $entry['status'] = 'expired';
                $expired[] = $entry;
            } elseif ($daysUntilExpiry <= 30) {
                $entry['status'] = 'expiring_soon';
                $expiringSoon[] = $entry;
            } else {
                $entry['status'] = 'expiring_later';
                $expiringLater[] = $entry;
            }
        }

        return [
            'report_type' => 'batch_expiry',
            'as_of_date' => $today,
            'days_ahead' => $daysAhead,
            'filters' => [
                'warehouse_id' => $warehouseId,
            ],
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'expiring_later' => $expiringLater,
            'summary' => [
                'expired_count' => count($expired),
                'expired_value' => collect($expired)->sum('total_value'),
                'expiring_soon_count' => count($expiringSoon),
                'expiring_soon_value' => collect($expiringSoon)->sum('total_value'),
                'expiring_later_count' => count($expiringLater),
                'expiring_later_value' => collect($expiringLater)->sum('total_value'),
                'total_at_risk' => collect([...$expired, ...$expiringSoon])->sum('total_value'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get unit cost based on valuation method.
     */
    protected function getUnitCost(object $stock, string $method): float
    {
        return match ($method) {
            'last_purchase' => (float) ($stock->last_purchase_price ?? $stock->average_cost ?? 0),
            'weighted_average', 'average' => (float) ($stock->average_cost ?? 0),
            default => (float) ($stock->average_cost ?? 0),
        };
    }

    /**
     * Get movement type label.
     */
    protected function getMovementTypeLabel(string $type): string
    {
        return match ($type) {
            'in' => 'Stock In',
            'out' => 'Stock Out',
            'purchase' => 'Purchase Receipt',
            'sale' => 'Sales Delivery',
            'return_in' => 'Sales Return',
            'return_out' => 'Purchase Return',
            'adjustment_in' => 'Adjustment (Increase)',
            'adjustment_out' => 'Adjustment (Decrease)',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
            'production_in' => 'Production Output',
            'production_out' => 'Production Input',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
