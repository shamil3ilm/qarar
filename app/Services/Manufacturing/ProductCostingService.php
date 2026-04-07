<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\CostComponent;
use App\Models\Manufacturing\CostingRun;
use App\Models\Manufacturing\CostingVersion;
use App\Models\Manufacturing\CostVariance;
use App\Models\Manufacturing\ProductStandardCost;
use App\Models\Manufacturing\WipValuation;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductCostingService
{
    /**
     * Roll up cost for a single product against a costing version.
     * Recursively walks the BOM: material cost from BOM lines, labor from
     * operations (work-center rate × time), overhead from BOM overhead_cost.
     */
    public function rollupCost(Product $product, CostingVersion $version): array
    {
        $bom = BomTemplate::active()
            ->where('product_id', $product->id)
            ->with(['lines.product', 'operations'])
            ->orderByDesc('version')
            ->first();

        if ($bom === null) {
            // No BOM — use the product's own cost fields if available
            $unitCost = (float) ($product->cost_price ?? $product->purchase_price ?? 0);

            return [
                'material_cost'       => $unitCost,
                'labor_cost'          => 0.0,
                'overhead_cost'       => 0.0,
                'subcontracting_cost' => 0.0,
                'total_standard_cost' => $unitCost,
                'cost_per_unit'       => $unitCost,
                'bom_id'              => null,
                'components'          => [],
            ];
        }

        $outputQty = (float) $bom->output_quantity;

        if (bccomp((string) $outputQty, '0', 6) <= 0) {
            throw new \InvalidArgumentException('Output quantity must be positive for cost calculation.');
        }

        // --- Material cost ---
        $materialCost = 0.0;
        $components   = [];

        foreach ($bom->lines as $line) {
            $adjustedQty = $line->getAdjustedQuantity((float) bcdiv('1', (string) $outputQty, 6));
            $unitCost    = (float) ($line->unit_cost ?? $line->product->purchase_price ?? 0);
            $lineCost    = (float) bcmul((string) $adjustedQty, (string) $unitCost, 4);
            $materialCost = (float) bcadd((string) $materialCost, (string) $lineCost, 4);

            $components[] = [
                'component_type' => 'material',
                'reference_type' => Product::class,
                'reference_id'   => $line->product_id,
                'description'    => $line->product->name . ($line->variant ? " ({$line->variant->name})" : ''),
                'quantity'       => $adjustedQty,
                'unit_cost'      => $unitCost,
                'total_cost'     => $lineCost,
            ];
        }

        // --- Labor cost ---
        $laborCost = 0.0;

        foreach ($bom->operations as $operation) {
            $hours     = (float) bcdiv(bcdiv((string) $operation->estimated_minutes, '60', 6), (string) $outputQty, 6);
            $rate      = (float) ($operation->labor_cost_per_hour ?? 0);
            $opCost    = (float) bcmul((string) $hours, (string) $rate, 4);
            $laborCost = (float) bcadd((string) $laborCost, (string) $opCost, 4);

            $components[] = [
                'component_type' => 'labor',
                'reference_type' => 'BomOperation',
                'reference_id'   => $operation->id,
                'description'    => $operation->name,
                'quantity'       => round($hours, 4),
                'unit_cost'      => $rate,
                'total_cost'     => $opCost,
            ];
        }

        // --- Overhead ---
        $overheadCost = (float) bcdiv((string) $bom->overhead_cost, (string) $outputQty, 4);

        if ($overheadCost > 0) {
            $components[] = [
                'component_type' => 'overhead',
                'reference_type' => null,
                'reference_id'   => null,
                'description'    => 'BOM overhead',
                'quantity'       => 1.0,
                'unit_cost'      => $overheadCost,
                'total_cost'     => $overheadCost,
            ];
        }

        $total = (float) bcadd(
            bcadd((string) $materialCost, (string) $laborCost, 4),
            (string) $overheadCost,
            4
        );

        return [
            'material_cost'       => $materialCost,
            'labor_cost'          => $laborCost,
            'overhead_cost'       => $overheadCost,
            'subcontracting_cost' => 0.0,
            'total_standard_cost' => $total,
            'cost_per_unit'       => $total,
            'bom_id'              => $bom->id,
            'components'          => $components,
        ];
    }

    /**
     * Run a full costing run: process all products with active BOMs for the
     * organisation and persist their standard costs under the given version.
     */
    public function runCostingRun(CostingVersion $version, Organization $organization): CostingRun
    {
        $run = CostingRun::create([
            'organization_id'    => $organization->id,
            'costing_version_id' => $version->id,
            'run_date'           => now()->toDateString(),
            'status'             => 'running',
            'created_by'         => auth()->id(),
        ]);

        $products = Product::where('organization_id', $organization->id)
            ->whereNull('deleted_at')
            ->get();

        $processed = 0;
        $failed    = 0;

        foreach ($products as $product) {
            try {
                DB::transaction(function () use ($product, $version) {
                    $breakdown = $this->rollupCost($product, $version);

                    // Upsert the standard cost record
                    $standardCost = ProductStandardCost::updateOrCreate(
                        [
                            'costing_version_id' => $version->id,
                            'product_id'         => $product->id,
                            'variant_id'         => null,
                        ],
                        [
                            'material_cost'       => $breakdown['material_cost'],
                            'labor_cost'          => $breakdown['labor_cost'],
                            'overhead_cost'       => $breakdown['overhead_cost'],
                            'subcontracting_cost' => $breakdown['subcontracting_cost'],
                            'total_standard_cost' => $breakdown['total_standard_cost'],
                            'cost_per_unit'       => $breakdown['cost_per_unit'],
                            'calculated_at'       => now(),
                            'bom_id'              => $breakdown['bom_id'],
                        ]
                    );

                    // Rebuild cost components
                    $standardCost->components()->delete();

                    foreach ($breakdown['components'] as $comp) {
                        CostComponent::create(array_merge(['standard_cost_id' => $standardCost->id], $comp));
                    }
                });

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Failed to cost product {$product->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $run->update([
            'products_processed' => $processed,
            'products_failed'    => $failed,
            'status'             => $failed === count($products) ? 'failed' : 'completed',
            'completed_at'       => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Calculate actual vs standard variance for a completed/in-progress work order.
     */
    public function calculateVariance(WorkOrder $workOrder): CostVariance
    {
        $version = CostingVersion::where('organization_id', $workOrder->organization_id)
            ->where('status', CostingVersion::STATUS_ACTIVE)
            ->latest('valid_from')
            ->first();

        // Fall back to any frozen version if no active one exists
        if ($version === null) {
            $version = CostingVersion::where('organization_id', $workOrder->organization_id)
                ->where('status', CostingVersion::STATUS_FROZEN)
                ->latest('valid_from')
                ->firstOrFail();
        }

        $standardCost = ProductStandardCost::where('costing_version_id', $version->id)
            ->where('product_id', $workOrder->product_id)
            ->first();

        $qty = max(1.0, (float) $workOrder->planned_quantity);

        $stdMaterial = $standardCost
            ? (float) bcmul((string) $standardCost->material_cost, (string) $qty, 4)
            : (float) $workOrder->estimated_material_cost;

        $stdLabor = $standardCost
            ? (float) bcmul((string) $standardCost->labor_cost, (string) $qty, 4)
            : (float) $workOrder->estimated_labor_cost;

        $stdOverhead = $standardCost
            ? (float) bcmul((string) $standardCost->overhead_cost, (string) $qty, 4)
            : (float) $workOrder->estimated_overhead_cost;

        $actMaterial = (float) $workOrder->actual_material_cost;
        $actLabor    = (float) $workOrder->actual_labor_cost;
        $actOverhead = (float) $workOrder->actual_overhead_cost;

        $totalStd = (float) bcadd(bcadd((string) $stdMaterial, (string) $stdLabor, 4), (string) $stdOverhead, 4);
        $totalAct = (float) bcadd(bcadd((string) $actMaterial, (string) $actLabor, 4), (string) $actOverhead, 4);
        $variance = (float) bcsub((string) $totalAct, (string) $totalStd, 4);
        $variancePct = $totalStd > 0
            ? round(($variance / $totalStd) * 100, 2)
            : 0.0;

        $now = Carbon::now();

        return CostVariance::updateOrCreate(
            [
                'work_order_id'      => $workOrder->id,
                'costing_version_id' => $version->id,
            ],
            [
                'organization_id'        => $workOrder->organization_id,
                'standard_material_cost' => $stdMaterial,
                'actual_material_cost'   => $actMaterial,
                'standard_labor_cost'    => $stdLabor,
                'actual_labor_cost'      => $actLabor,
                'standard_overhead_cost' => $stdOverhead,
                'actual_overhead_cost'   => $actOverhead,
                'total_standard'         => $totalStd,
                'total_actual'           => $totalAct,
                'total_variance'         => $variance,
                'variance_pct'           => $variancePct,
                'period_year'            => (int) $now->format('Y'),
                'period_month'           => (int) $now->format('n'),
            ]
        );
    }

    /**
     * Valuate WIP for all open work orders in the organisation on a given date.
     */
    public function valuateWip(Organization $organization, string $valuationDate): array
    {
        $openOrders = WorkOrder::where('organization_id', $organization->id)
            ->whereIn('status', [
                WorkOrder::STATUS_PENDING,
                WorkOrder::STATUS_SCHEDULED,
                WorkOrder::STATUS_IN_PROGRESS,
            ])
            ->with(['materials', 'operations.bomOperation'])
            ->get();

        $valuations = [];

        foreach ($openOrders as $workOrder) {
            $valuation = $this->valuateSingleWip($workOrder, $valuationDate);
            $valuations[] = $valuation;
        }

        return $valuations;
    }

    /**
     * Valuate WIP for a single work order.
     */
    protected function valuateSingleWip(WorkOrder $workOrder, string $valuationDate): WipValuation
    {
        $plannedQty   = max(1.0, (float) $workOrder->planned_quantity);
        $completedQty = (float) $workOrder->produced_quantity;
        $wipQty       = max(0.0, $plannedQty - $completedQty);
        $wipRatio     = $wipQty / $plannedQty;

        $materialWip  = (float) bcmul((string) $workOrder->actual_material_cost, (string) $wipRatio, 4);
        $laborWip     = (float) bcmul((string) $workOrder->actual_labor_cost, (string) $wipRatio, 4);
        $overheadWip  = (float) bcmul((string) $workOrder->estimated_overhead_cost, (string) $wipRatio, 4);
        $totalWip     = (float) bcadd(bcadd((string) $materialWip, (string) $laborWip, 4), (string) $overheadWip, 4);

        return WipValuation::updateOrCreate(
            [
                'work_order_id'  => $workOrder->id,
                'valuation_date' => $valuationDate,
            ],
            [
                'organization_id' => $workOrder->organization_id,
                'completed_qty'   => $completedQty,
                'wip_qty'         => $wipQty,
                'material_wip'    => $materialWip,
                'labor_wip'       => $laborWip,
                'overhead_wip'    => $overheadWip,
                'total_wip'       => $totalWip,
            ]
        );
    }
}
