<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\DemandForecast;
use App\Models\Manufacturing\MrpCapacityRequirement;
use App\Models\Manufacturing\MrpDemandItem;
use App\Models\Manufacturing\MrpPlannedOrder;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCapacity;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\PurchaseRequisitionLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\Purchase\SourceListService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MrpService
{
    private const MAX_BOM_DEPTH = 50;

    public function __construct(
        private SourceListService $sourceListService
    ) {}
    /**
     * Execute an MRP run for the authenticated organization.
     *
     * Steps:
     *   1. Create the run record in pending state.
     *   2. Collect demand from sales orders, forecasts, and safety stock.
     *   3. For each product with net demand, create planned orders.
     *   4. Explode BOMs to cover component demand recursively.
     *   5. Mark the run as completed.
     */
    public function runMrp(array $data, int $userId): MrpRun
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId = auth()->user()->organization_id ?? $data['organization_id'];
            $horizonDays = (int) ($data['planning_horizon_days'] ?? 30);
            $horizonEnd = Carbon::now()->addDays($horizonDays)->toDateString();

            $run = MrpRun::create([
                'organization_id'        => $orgId,
                'run_date'               => now(),
                'planning_horizon_days'  => $horizonDays,
                'status'                 => MrpRun::STATUS_RUNNING,
                'run_by'                 => $userId,
            ]);

            try {
                // Cancel stale planned (but not firmed) orders from previous runs
                MrpPlannedOrder::where('organization_id', $orgId)
                    ->where('mrp_run_id', '!=', $run->id)
                    ->where('status', MrpPlannedOrder::STATUS_PLANNED)
                    ->update(['status' => MrpPlannedOrder::STATUS_CANCELLED]);

                // Step 2: Collect all demand items
                $demandByProduct = $this->collectDemand($run, $orgId, $horizonEnd);

                // Step 3 & 4: Plan orders covering net requirements and explode BOMs
                $plannedCount = $this->planOrders($run, $orgId, $demandByProduct);

                // Step 5: Mark as completed
                $run->update([
                    'status'                  => MrpRun::STATUS_COMPLETED,
                    'total_products_analyzed' => $demandByProduct->count(),
                    'total_planned_orders'    => $plannedCount,
                    'completed_at'            => now(),
                ]);
            } catch (\Throwable $e) {
                $run->update([
                    'status'        => MrpRun::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now(),
                ]);

                Log::error('MRP run failed', [
                    'run_id' => $run->id,
                    'error'  => $e->getMessage(),
                ]);

                throw $e;
            }

            return $run->fresh(['plannedOrders.product', 'runBy']);
        });
    }

    /**
     * Firm a planned order so it cannot be automatically replaced.
     */
    public function firmPlannedOrder(MrpPlannedOrder $order, int $userId): MrpPlannedOrder
    {
        if (!$order->canBeFirmed()) {
            throw new \InvalidArgumentException('Only planned orders in "planned" status can be firmed.');
        }

        return $order->firm($userId);
    }

    /**
     * Convert a planned order to a purchase order or work order based on its type.
     */
    public function convertToOrder(MrpPlannedOrder $order, int $userId): Model
    {
        if (!$order->canBeConverted()) {
            throw new \InvalidArgumentException('Only planned or firmed orders can be converted.');
        }

        return match ($order->order_type) {
            MrpPlannedOrder::TYPE_PURCHASE   => $order->convertToPurchaseOrder($userId),
            MrpPlannedOrder::TYPE_PRODUCTION => $order->convertToWorkOrder($userId),
            default                          => throw new \InvalidArgumentException("Cannot convert order of type '{$order->order_type}'."),
        };
    }

    /**
     * Create or update a demand forecast for a product.
     */
    public function setForecast(array $data, int $userId): DemandForecast
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;

            return DemandForecast::updateOrCreate(
                [
                    'organization_id' => $data['organization_id'],
                    'product_id'      => $data['product_id'],
                    'forecast_date'   => $data['forecast_date'],
                ],
                $data
            );
        });
    }

    /**
     * Get forecast accuracy statistics for an organization within a date range.
     */
    public function getForecastAccuracy(int $orgId, string $from, string $to): array
    {
        $forecasts = DemandForecast::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->forPeriod($from, $to)
            ->whereNotNull('actual_quantity')
            ->with('product:id,name,sku')
            ->get();

        if ($forecasts->isEmpty()) {
            return [
                'total_forecasts'  => 0,
                'average_accuracy' => null,
                'by_product'       => [],
            ];
        }

        $byProduct = $forecasts->groupBy('product_id')->map(function (Collection $items) {
            $accuracies = $items->map(fn ($f) => $f->getAccuracy())->filter(fn ($a) => $a !== null);

            return [
                'product_id'       => $items->first()->product_id,
                'product_name'     => $items->first()->product?->name,
                'sku'              => $items->first()->product?->sku,
                'total_forecasts'  => $items->count(),
                'average_accuracy' => $accuracies->isNotEmpty() ? round($accuracies->average(), 2) : null,
            ];
        })->values()->all();

        $allAccuracies = $forecasts->map(fn ($f) => $f->getAccuracy())->filter(fn ($a) => $a !== null);

        return [
            'total_forecasts'  => $forecasts->count(),
            'average_accuracy' => $allAccuracies->isNotEmpty() ? round($allAccuracies->average(), 2) : null,
            'by_product'       => $byProduct,
        ];
    }

    /**
     * Get current MRP exceptions:
     * - Late planned orders (planned_end_date in the past)
     * - Unfirmed planned orders (still in "planned" status, action required)
     * - Demand exceeding supply (products with active demand but no planned orders)
     */
    public function getMrpExceptions(int $orgId): array
    {
        $today = now()->toDateString();

        $late = MrpPlannedOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereIn('status', [MrpPlannedOrder::STATUS_PLANNED, MrpPlannedOrder::STATUS_FIRMED])
            ->where('planned_end_date', '<', $today)
            ->with('product:id,name,sku')
            ->limit(100)
            ->get()
            ->map(fn ($o) => [
                'id'               => $o->id,
                'uuid'             => $o->uuid,
                'product_name'     => $o->product?->name,
                'sku'              => $o->product?->sku,
                'planned_end_date' => $o->planned_end_date?->toDateString(),
                'order_type'       => $o->order_type,
                'planned_quantity' => $o->planned_quantity,
                'status'           => $o->status,
            ])->all();

        $unfirmed = MrpPlannedOrder::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('status', MrpPlannedOrder::STATUS_PLANNED)
            ->where('planned_start_date', '<=', now()->addDays(7)->toDateString())
            ->with('product:id,name,sku')
            ->limit(100)
            ->get()
            ->map(fn ($o) => [
                'id'                 => $o->id,
                'uuid'               => $o->uuid,
                'product_name'       => $o->product?->name,
                'sku'                => $o->product?->sku,
                'planned_start_date' => $o->planned_start_date?->toDateString(),
                'planned_quantity'   => $o->planned_quantity,
                'order_type'         => $o->order_type,
            ])->all();

        return [
            'late_planned_orders'    => $late,
            'late_count'             => count($late),
            'unfirmed_near_term'     => $unfirmed,
            'unfirmed_count'         => count($unfirmed),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Collect demand from all sources and store demand items on the run.
     * Returns a Collection keyed by product_id => total required quantity.
     */
    private function collectDemand(MrpRun $run, int $orgId, string $horizonEnd): Collection
    {
        $demand = collect();

        // 1) Open sales order lines due within the horizon
        SalesOrderLine::withoutGlobalScope('organization')
            ->whereHas('salesOrder', function ($q) use ($orgId, $horizonEnd) {
                $q->withoutGlobalScope('organization')
                    ->where('organization_id', $orgId)
                    ->whereIn('status', [
                        SalesOrder::STATUS_CONFIRMED,
                        SalesOrder::STATUS_PROCESSING,
                    ])
                    ->where(function ($q2) use ($horizonEnd) {
                        $q2->whereNull('expected_delivery_date')
                            ->orWhere('expected_delivery_date', '<=', $horizonEnd);
                    });
            })
            ->with('salesOrder:id,expected_delivery_date')
            ->chunkById(200, function ($salesLines) use ($run, $horizonEnd, &$demand) {
                foreach ($salesLines as $line) {
                    $qty = max(0, (float) $line->quantity - (float) ($line->quantity_delivered ?? 0));

                    if ($qty <= 0) {
                        continue;
                    }

                    MrpDemandItem::create([
                        'mrp_run_id'        => $run->id,
                        'product_id'        => $line->product_id,
                        'source_type'       => MrpDemandItem::SOURCE_SALES_ORDER,
                        'source_id'         => $line->id,
                        'required_date'     => $line->salesOrder->expected_delivery_date ?? $horizonEnd,
                        'required_quantity' => $qty,
                    ]);

                    $demand->put($line->product_id, ($demand->get($line->product_id, 0.0) + $qty));
                }
            });

        // 2) Demand forecasts within the horizon
        DemandForecast::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('forecast_date', '<=', $horizonEnd)
            ->where('forecast_date', '>=', now()->toDateString())
            ->chunkById(200, function ($forecasts) use ($run, &$demand) {
                foreach ($forecasts as $forecast) {
                    $qty = (float) $forecast->forecast_quantity;

                    MrpDemandItem::create([
                        'mrp_run_id'        => $run->id,
                        'product_id'        => $forecast->product_id,
                        'source_type'       => MrpDemandItem::SOURCE_FORECAST,
                        'source_id'         => $forecast->id,
                        'required_date'     => $forecast->forecast_date->toDateString(),
                        'required_quantity' => $qty,
                    ]);

                    $demand->put($forecast->product_id, ($demand->get($forecast->product_id, 0.0) + $qty));
                }
            });

        // 3) Safety stock requirements — products whose stock is below reorder level
        StockLevel::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereRaw('quantity < COALESCE(reorder_level, 0)')
            ->whereRaw('reorder_level > 0')
            ->chunkById(200, function ($lowStockItems) use ($run, &$demand) {
        foreach ($lowStockItems as $sl) {
            $reorderLevel = (string) ($sl->reorder_level ?? '0');

            // Skip items with no meaningful reorder level set
            if (bccomp($reorderLevel, '0', 4) <= 0) {
                continue;
            }

            $reorderQty = (float) ($sl->reorder_quantity ?: $sl->reorder_level ?? 1);
            $gap        = bcsub($reorderLevel, (string) $sl->quantity, 4);

            if (bccomp($gap, '0', 4) <= 0) {
                continue;
            }

            $gapFloat = (float) $gap;

            MrpDemandItem::create([
                'mrp_run_id'        => $run->id,
                'product_id'        => $sl->product_id,
                'source_type'       => MrpDemandItem::SOURCE_SAFETY_STOCK,
                'source_id'         => null,
                'required_date'     => now()->toDateString(),
                'required_quantity' => $gapFloat,
            ]);

            $demand->put($sl->product_id, ($demand->get($sl->product_id, 0.0) + $gapFloat));
        }
        }); // end chunkById for safety stock

        return $demand;
    }

    /**
     * For each product with demand, compute net requirement and create planned orders.
     * Also explode BOMs to plan component orders recursively.
     *
     * @param  Collection<int, float>  $demandByProduct
     */
    private function planOrders(MrpRun $run, int $orgId, Collection $demandByProduct): int
    {
        $plannedCount = 0;
        $visited      = [];  // Guard against infinite BOM recursion

        foreach ($demandByProduct as $productId => $totalDemand) {
            $plannedCount += $this->planForProduct(
                $run,
                $orgId,
                $productId,
                $totalDemand,
                $visited
            );
        }

        return $plannedCount;
    }

    /**
     * Plan orders for a single product and recursively for its BOM components.
     *
     * @param  array<int, bool>  $visited
     */
    private function planForProduct(
        MrpRun $run,
        int $orgId,
        int $productId,
        float $totalDemand,
        array &$visited,
        int $depth = 0
    ): int {
        if (isset($visited[$productId])) {
            return 0; // Already processed — avoid circular BOM explosion
        }

        $visited[$productId] = true;
        $plannedCount        = 0;

        // Get current available stock across all warehouses (quantity minus reserved)
        $currentStock = (float) StockLevel::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available')
            ->value('available');

        $netRequirement = (float) bcsub((string) $totalDemand, (string) $currentStock, 4);

        if ($netRequirement > 0) {
            // Round up to order multiple
            $product        = Product::withoutGlobalScope('organization')->find($productId);
            $orderMultiple  = (float) ($product->reorder_quantity ?? 1);
            $orderMultiple  = $orderMultiple > 0 ? $orderMultiple : 1.0;
            $units          = (int) ceil((float) bcdiv((string) $netRequirement, (string) $orderMultiple, 4));
            $plannedQty     = (float) bcmul((string) $units, (string) $orderMultiple, 4);

            // Determine order type: if a BOM exists, produce; otherwise purchase
            $hasBom    = BomTemplate::withoutGlobalScope('organization')
                ->where('organization_id', $orgId)
                ->where('product_id', $productId)
                ->where('status', BomTemplate::STATUS_ACTIVE)
                ->exists();

            $orderType = $hasBom ? MrpPlannedOrder::TYPE_PRODUCTION : MrpPlannedOrder::TYPE_PURCHASE;
            // Use product-level lead time when available; fall back to a configurable default
            $leadDays  = (int) ($product->lead_time_days ?? $product->default_supplier_lead_days ?? config('erp.default_lead_time_days', 7));

            MrpPlannedOrder::create([
                'organization_id'    => $orgId,
                'mrp_run_id'         => $run->id,
                'product_id'         => $productId,
                'order_type'         => $orderType,
                'planned_quantity'   => $plannedQty,
                'planned_start_date' => now()->toDateString(),
                'planned_end_date'   => now()->addDays($leadDays)->toDateString(),
                'status'             => MrpPlannedOrder::STATUS_PLANNED,
            ]);

            $plannedCount++;

            // BOM explosion: if the product is produced, plan for its components
            if ($hasBom) {
                $plannedCount += $this->explodeBom($run, $orgId, $productId, $plannedQty, $visited, $depth);
            }
        }

        return $plannedCount;
    }

    /**
     * Recursively explode a BOM to plan component orders.
     *
     * @param  array<int, bool>  $visited
     */
    private function explodeBom(
        MrpRun $run,
        int $orgId,
        int $productId,
        float $quantity,
        array &$visited,
        int $depth = 0
    ): int {
        if ($depth >= self::MAX_BOM_DEPTH) {
            throw new \RuntimeException('BOM depth limit exceeded — possible circular reference.');
        }
        $bom = BomTemplate::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('status', BomTemplate::STATUS_ACTIVE)
            ->with('lines')
            ->first();

        if (!$bom) {
            return 0;
        }

        $plannedCount  = 0;
        $outputQty     = (float) ($bom->output_quantity ?: 1);
        $multiplier    = $quantity / $outputQty;

        foreach ($bom->lines as $line) {
            $componentDemand = (float) $line->getAdjustedQuantity($multiplier);

            // Add this component's demand to the run items
            MrpDemandItem::create([
                'mrp_run_id'        => $run->id,
                'product_id'        => $line->product_id,
                'source_type'       => MrpDemandItem::SOURCE_BOM,
                'source_id'         => $bom->id,
                'required_date'     => now()->toDateString(),
                'required_quantity' => $componentDemand,
            ]);

            $plannedCount += $this->planForProduct(
                $run,
                $orgId,
                $line->product_id,
                $componentDemand,
                $visited,
                $depth + 1
            );
        }

        return $plannedCount;
    }

    // -------------------------------------------------------------------------
    // MRP → Capacity Requirements Planning (SAP PP-CRP)
    // -------------------------------------------------------------------------

    /**
     * Run a Capacity Requirements Planning (CRP) check against a list of
     * planned orders, persisting one MrpCapacityRequirement record per order
     * that has a work-center assignment.
     *
     * Steps:
     *   1. For each planned order, derive the work center and hours_per_unit
     *      from its BOM routing (RoutingOperation) when available, otherwise
     *      fall back to 1.0 h/unit.
     *   2. Fetch the active WorkCenterCapacity for the planned start date.
     *   3. Compute available_hours and load_pct; persist the record.
     *   4. Return a structured summary including overloaded work centers.
     *
     * @param  array<int, MrpPlannedOrder>|Collection<int, MrpPlannedOrder>  $plannedOrders
     * @return array{requirements: array<int, mixed>, overloaded_work_centers: array<int, mixed>, feasible: bool}
     */
    public function runCapacityCheck(
        array|Collection $plannedOrders,
        Carbon $planningHorizon
    ): array {
        $plannedOrders = collect($plannedOrders);

        if ($plannedOrders->isEmpty()) {
            return [
                'requirements'           => [],
                'overloaded_work_centers' => [],
                'feasible'               => true,
            ];
        }

        $orgId        = (int) $plannedOrders->first()->organization_id;
        $requirements = [];
        $allFeasible  = true;

        // Pre-load active work centers for this org so we can map BOM routing
        $workCenters = WorkCenter::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use (
            $plannedOrders,
            $orgId,
            $planningHorizon,
            $workCenters,
            &$requirements,
            &$allFeasible,
        ): void {
            foreach ($plannedOrders as $order) {
                if ($order->product_id === null) {
                    continue;
                }

                $requiredDate = $order->planned_start_date instanceof Carbon
                    ? $order->planned_start_date
                    : Carbon::parse((string) $order->planned_start_date);

                // Skip orders beyond the planning horizon
                if ($requiredDate->gt($planningHorizon)) {
                    continue;
                }

                // Try to find a routing operation for this product → work center
                // Use the first (lowest sequence_number) operation on the active routing.
                $routing = \App\Models\Manufacturing\RoutingOperation::withoutGlobalScopes()
                    ->whereHas('routing', function ($q) use ($orgId, $order): void {
                        $q->withoutGlobalScopes()
                          ->where('organization_id', $orgId)
                          ->where('product_id', $order->product_id);
                    })
                    ->with('workCenter')
                    ->orderBy('sequence_number')
                    ->first();

                // Determine work center and hours-per-unit.
                // hours_per_unit = machine_time + labor_time (both already expressed per unit).
                if ($routing !== null && $routing->work_center_id !== null) {
                    $workCenterId = (int) $routing->work_center_id;
                    $hoursPerUnit = (float) $routing->machine_time + (float) $routing->labor_time;
                    $hoursPerUnit = $hoursPerUnit > 0.0 ? $hoursPerUnit : 1.0;
                } else {
                    // No routing: pick the first active work center or skip
                    $firstWc = $workCenters->first();
                    if ($firstWc === null) {
                        continue;
                    }
                    $workCenterId = (int) $firstWc->id;
                    $hoursPerUnit = 1.0;
                }

                $requiredHours = (float) $order->planned_quantity * $hoursPerUnit;

                // Fetch active capacity record for the required date
                $capacity = WorkCenterCapacity::withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('work_center_id', $workCenterId)
                    ->activeOn($requiredDate->toDateString())
                    ->first();

                $availableHours = $capacity !== null
                    ? $capacity->effectiveHoursPerDay()
                    : 0.0;

                $loadPct = $availableHours > 0.0
                    ? round(($requiredHours / $availableHours) * 100, 2)
                    : ($requiredHours > 0 ? 999.99 : 0.0);

                $status = $loadPct > 100.0
                    ? MrpCapacityRequirement::STATUS_OVERLOADED
                    : MrpCapacityRequirement::STATUS_FEASIBLE;

                if ($status === MrpCapacityRequirement::STATUS_OVERLOADED) {
                    $allFeasible = false;
                }

                $req = MrpCapacityRequirement::create([
                    'organization_id' => $orgId,
                    'mrp_run_id'      => $order->mrp_run_id,
                    'work_center_id'  => $workCenterId,
                    'planned_order_id' => $order->id,
                    'required_date'   => $requiredDate->toDateString(),
                    'required_hours'  => $requiredHours,
                    'available_hours' => $availableHours,
                    'load_pct'        => $loadPct,
                    'status'          => $status,
                ]);

                $requirements[] = [
                    'id'               => $req->id,
                    'uuid'             => $req->uuid,
                    'planned_order_id' => $order->id,
                    'work_center_id'   => $workCenterId,
                    'work_center_name' => $workCenters->get($workCenterId)?->name,
                    'required_date'    => $requiredDate->toDateString(),
                    'required_hours'   => $requiredHours,
                    'available_hours'  => $availableHours,
                    'load_pct'         => $loadPct,
                    'status'           => $status,
                ];
            }
        });

        $overloaded = collect($requirements)
            ->where('status', MrpCapacityRequirement::STATUS_OVERLOADED)
            ->groupBy('work_center_id')
            ->map(function (Collection $items): array {
                return [
                    'work_center_id'   => $items->first()['work_center_id'],
                    'work_center_name' => $items->first()['work_center_name'],
                    'overloaded_count' => $items->count(),
                    'max_load_pct'     => $items->max('load_pct'),
                ];
            })
            ->values()
            ->all();

        return [
            'requirements'            => $requirements,
            'overloaded_work_centers' => $overloaded,
            'feasible'                => $allFeasible,
        ];
    }

    /**
     * Return aggregated capacity load per work center per ISO week for a
     * given date range.
     *
     * Groups persisted MrpCapacityRequirement records by work_center_id and
     * ISO week number, summing required_hours and averaging available_hours.
     *
     * @return array<int, array{work_center_id: int, work_center_name: string|null, weeks: array<int, mixed>}>
     */
    public function getCapacityLoad(int $orgId, Carbon $fromDate, Carbon $toDate): array
    {
        // Aggregate at DB level — GROUP BY work_center + ISO week, no full collection in memory.
        $rows = MrpCapacityRequirement::withoutGlobalScopes()
            ->where('mrp_capacity_requirements.organization_id', $orgId)
            ->whereDate('required_date', '>=', $fromDate->toDateString())
            ->whereDate('required_date', '<=', $toDate->toDateString())
            ->join('work_centers', 'work_centers.id', '=', 'mrp_capacity_requirements.work_center_id')
            ->selectRaw(
                'mrp_capacity_requirements.work_center_id,
                 work_centers.name  AS work_center_name,
                 work_centers.code  AS work_center_code,
                 DATE_FORMAT(required_date, \'%x-%V\') AS year_week,
                 SUM(required_hours)  AS required_hours,
                 SUM(available_hours) AS available_hours,
                 MAX(CASE WHEN required_hours > available_hours THEN 1 ELSE 0 END) AS overloaded'
            )
            ->groupBy('mrp_capacity_requirements.work_center_id', 'work_centers.name', 'work_centers.code', 'year_week')
            ->orderBy('mrp_capacity_requirements.work_center_id')
            ->orderBy('year_week')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $wcId = (int) $row->work_center_id;
            if (!isset($grouped[$wcId])) {
                $grouped[$wcId] = [
                    'work_center_id'   => $wcId,
                    'work_center_name' => $row->work_center_name,
                    'work_center_code' => $row->work_center_code,
                    'weeks'            => [],
                ];
            }

            $req  = (float) $row->required_hours;
            $avail = (float) $row->available_hours;
            $grouped[$wcId]['weeks'][] = [
                'year_week'       => $row->year_week,
                'required_hours'  => $req,
                'available_hours' => $avail,
                'load_pct'        => $avail > 0.0
                    ? round(($req / $avail) * 100, 2)
                    : ($req > 0 ? 999.99 : 0.0),
                'overloaded'      => (bool) $row->overloaded,
            ];
        }

        return array_values($grouped);
    }

    // -------------------------------------------------------------------------
    // MRP → Auto Purchase Requisition
    // -------------------------------------------------------------------------

    /**
     * Convert purchase-type planned orders from an MRP run into a single
     * PurchaseRequisition with one line per planned order.
     *
     * @param  int[]|null  $plannedOrderIds  Restrict conversion to these IDs;
     *                                       null converts all eligible orders.
     * @return array{requisition: PurchaseRequisition, converted_count: int, skipped_count: int}
     */
    public function convertPlannedOrdersToPR(MrpRun $run, ?array $plannedOrderIds, int $userId): array
    {
        return DB::transaction(function () use ($run, $plannedOrderIds, $userId): array {
            $query = MrpPlannedOrder::where('mrp_run_id', $run->id)
                ->where('organization_id', $run->organization_id)
                ->where('order_type', MrpPlannedOrder::TYPE_PURCHASE)
                ->whereIn('status', [MrpPlannedOrder::STATUS_PLANNED, MrpPlannedOrder::STATUS_FIRMED])
                ->whereNull('purchase_requisition_id')
                ->with('product');

            if ($plannedOrderIds !== null) {
                $query->whereIn('id', $plannedOrderIds);
            }

            if (!$query->exists()) {
                throw new \InvalidArgumentException(
                    'No eligible purchase planned orders found for this MRP run.'
                );
            }

            // Pre-fetch all unique product IDs cheaply (IDs only, no model hydration).
            $productIds = (clone $query)->distinct()->pluck('product_id')
                ->filter()->values()->toArray();

            // Auto-select preferred vendors for all unique products in one pass.
            $vendorsByProduct = $this->sourceListService->autoSelectVendors($productIds);

            // Create one PR header for the entire run.
            $runDate  = $run->run_date instanceof \DateTimeInterface
                ? $run->run_date->format('Y-m-d')
                : now()->toDateString();

            $requisition = PurchaseRequisition::create([
                'organization_id'   => $run->organization_id,
                'requisition_date'  => now()->toDateString(),
                'requisition_type'  => 'purchase',
                'status'            => PurchaseRequisition::STATUS_DRAFT,
                'requested_by'      => $userId,
                'notes'             => "MRP Auto-PR - Run #{$run->id} - {$runDate}",
            ]);

            $convertedCount = 0;
            $skippedCount   = 0;

            // Process in chunks to avoid loading all planned orders into memory at once.
            $query->chunkById(100, function ($orders) use ($requisition, $vendorsByProduct, &$convertedCount, &$skippedCount) {
                foreach ($orders as $order) {
                    if ($order->product_id === null) {
                        $skippedCount++;
                        continue;
                    }

                    $preferredVendorId = $vendorsByProduct[$order->product_id] ?? null;

                    PurchaseRequisitionLine::create([
                        'requisition_id'       => $requisition->id,
                        'product_id'           => $order->product_id,
                        'quantity'             => $order->planned_quantity,
                        'required_by_date'     => $order->planned_end_date?->toDateString(),
                        'preferred_vendor_id'  => $preferredVendorId,
                        'status'               => 'open',
                        'notes'                => "MRP planned order #{$order->uuid}",
                    ]);

                    $order->update([
                        'status'                  => MrpPlannedOrder::STATUS_CONVERTED,
                        'converted_at'            => now(),
                        'converted_to_type'       => PurchaseRequisition::class,
                        'converted_to_id'         => $requisition->id,
                        'purchase_requisition_id' => $requisition->id,
                    ]);

                    $convertedCount++;
                }
            });

            return [
                'requisition'     => $requisition->load('lines.product'),
                'converted_count' => $convertedCount,
                'skipped_count'   => $skippedCount,
            ];
        });
    }
}
