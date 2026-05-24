<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\MaterialTransaction;
use App\Models\Manufacturing\ProductionLog;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderMaterial;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Models\Inventory\StockMovement;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
        private BomService $bomService,
        private JournalService $journalService
    ) {}

    /**
     * Create a work order from a BOM template.
     */
    public function create(BomTemplate $bom, array $data, int $userId): WorkOrder
    {
        $bom->loadMissing(['lines.product', 'operations']);

        if (!$bom->isActive()) {
            throw new \InvalidArgumentException('BOM template must be active to create a work order.');
        }

        return DB::transaction(function () use ($bom, $data, $userId) {
            $quantity = (float) $data['planned_quantity'];

            // Calculate costs
            $costs = $bom->calculateTotalCost($quantity);

            $workOrder = WorkOrder::create([
                'organization_id' => auth()->user()->organization_id,
                'branch_id' => $data['branch_id'] ?? null,
                'work_order_number' => $this->numberGenerator->generate('WO'),
                'bom_template_id' => $bom->id,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sales_order_line_id' => $data['sales_order_line_id'] ?? null,
                'product_id' => $bom->product_id,
                'variant_id' => $bom->variant_id,
                'planned_quantity' => $quantity,
                'produced_quantity' => 0,
                'rejected_quantity' => 0,
                'unit_id' => $bom->output_unit_id,
                'planned_start_date' => $data['planned_start_date'],
                'planned_end_date' => $data['planned_end_date'],
                'source_warehouse_id' => $data['source_warehouse_id'] ?? $bom->default_warehouse_id,
                'target_warehouse_id' => $data['target_warehouse_id'] ?? $bom->default_warehouse_id,
                'estimated_material_cost' => $costs['material_cost'],
                'estimated_labor_cost' => $costs['labor_cost'],
                'estimated_overhead_cost' => $costs['overhead_cost'],
                'status' => WorkOrder::STATUS_DRAFT,
                'priority' => $data['priority'] ?? WorkOrder::PRIORITY_NORMAL,
                'assigned_to' => $data['assigned_to'] ?? null,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Create work order materials from BOM lines
            $this->createMaterialsFromBom($workOrder, $bom, $quantity);

            // Create work order operations from BOM operations
            $this->createOperationsFromBom($workOrder, $bom);

            return $workOrder->fresh(['materials.product', 'operations', 'bomTemplate']);
        });
    }

    /**
     * Create work order materials from BOM lines.
     */
    protected function createMaterialsFromBom(WorkOrder $workOrder, BomTemplate $bom, float $quantity): void
    {
        $multiplier = (float) bcdiv((string) $quantity, (string) $bom->output_quantity, 6);

        foreach ($bom->lines as $line) {
            $requiredQuantity = $line->getAdjustedQuantity($multiplier);

            WorkOrderMaterial::create([
                'work_order_id' => $workOrder->id,
                'bom_line_id' => $line->id,
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'description' => $line->description,
                'required_quantity' => $requiredQuantity,
                'issued_quantity' => 0,
                'consumed_quantity' => 0,
                'returned_quantity' => 0,
                'wastage_quantity' => 0,
                'unit_id' => $line->unit_id,
                'unit_cost' => $line->unit_cost ?? $line->product->purchase_price ?? 0,
                'total_cost' => 0,
                'warehouse_id' => $line->warehouse_id ?? $workOrder->source_warehouse_id,
                'line_order' => $line->line_order,
            ]);
        }
    }

    /**
     * Create work order operations from BOM operations.
     */
    protected function createOperationsFromBom(WorkOrder $workOrder, BomTemplate $bom): void
    {
        foreach ($bom->operations as $operation) {
            WorkOrderOperation::create([
                'work_order_id' => $workOrder->id,
                'bom_operation_id' => $operation->id,
                'name' => $operation->name,
                'instructions' => $operation->instructions,
                'sequence' => $operation->sequence,
                'estimated_minutes' => $operation->estimated_minutes,
                'actual_minutes' => 0,
                'status' => WorkOrderOperation::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Update a work order.
     */
    public function update(WorkOrder $workOrder, array $data): WorkOrder
    {
        if (!$workOrder->canBeEdited()) {
            throw new \InvalidArgumentException('Work order cannot be edited in its current status.');
        }

        $workOrder->update($data);

        return $workOrder->fresh();
    }

    /**
     * Release work order for production.
     */
    public function release(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->isDraft()) {
            throw new \InvalidArgumentException('Only draft work orders can be released.');
        }

        $workOrder->loadMissing(['bomTemplate.lines.product', 'bomTemplate.operations']);

        // Check material availability
        $availability = $this->bomService->checkAvailability(
            $workOrder->bomTemplate,
            (float) $workOrder->planned_quantity,
            $workOrder->source_warehouse_id
        );

        if ($availability['critical_shortage']) {
            throw new \InvalidArgumentException('Cannot release work order. Critical materials are not available.');
        }

        $workOrder->update(['status' => WorkOrder::STATUS_RELEASED]);

        return $workOrder->fresh();
    }

    /**
     * Schedule a work order.
     */
    public function schedule(WorkOrder $workOrder, array $data): WorkOrder
    {
        if (!$workOrder->isPending()) {
            throw new \InvalidArgumentException('Only pending work orders can be scheduled.');
        }

        $workOrder->update([
            'status' => WorkOrder::STATUS_SCHEDULED,
            'planned_start_date' => $data['planned_start_date'] ?? $workOrder->planned_start_date,
            'planned_end_date' => $data['planned_end_date'] ?? $workOrder->planned_end_date,
            'assigned_to' => $data['assigned_to'] ?? $workOrder->assigned_to,
        ]);

        return $workOrder->fresh();
    }

    /**
     * Start a work order.
     */
    public function start(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->canBeStarted()) {
            throw new \InvalidArgumentException('Work order cannot be started in its current status.');
        }

        $workOrder->start();

        return $workOrder->fresh();
    }

    /**
     * Issue materials to work order.
     */
    public function issueMaterials(WorkOrder $workOrder, array $issues, int $userId): WorkOrder
    {
        if (!$workOrder->isInProgress()) {
            throw new \InvalidArgumentException('Materials can only be issued to in-progress work orders.');
        }

        return DB::transaction(function () use ($workOrder, $issues, $userId) {
            // Preload all requested materials in one query to avoid N+1
            $materialIds = array_column($issues, 'work_order_material_id');
            $materials = WorkOrderMaterial::whereIn('id', $materialIds)->get()->keyBy('id');

            foreach ($issues as $issue) {
                $material = $materials->get($issue['work_order_material_id'])
                    ?? throw new \InvalidArgumentException("Material {$issue['work_order_material_id']} not found.");

                if ($material->work_order_id !== $workOrder->id) {
                    throw new \InvalidArgumentException('Material does not belong to this work order.');
                }

                $quantity = (float) $issue['quantity'];
                $requiredQty = (float) $material->required_quantity;
                if (bccomp((string) $quantity, (string) $requiredQty, 4) > 0) {
                    throw new \RuntimeException('Cannot issue more material than required.');
                }

                $warehouseId = $issue['warehouse_id'] ?? $material->warehouse_id;

                // Record stock movement (out) - only if warehouse is set
                if ($warehouseId) {
                    $this->stockService->recordMovement(
                        productId: $material->product_id,
                        warehouseId: $warehouseId,
                        movementType: StockMovement::TYPE_MATERIAL_ISSUE,
                        direction: StockMovement::DIRECTION_OUT,
                        quantity: $quantity,
                        unitCost: (float) $material->unit_cost,
                        referenceType: WorkOrder::class,
                        referenceId: $workOrder->id,
                        notes: "Material issue for WO: {$workOrder->work_order_number}",
                    );
                }

                // Record material transaction
                $transaction = MaterialTransaction::create([
                    'organization_id' => $workOrder->organization_id,
                    'work_order_id' => $workOrder->id,
                    'work_order_material_id' => $material->id,
                    'transaction_type' => MaterialTransaction::TYPE_ISSUE,
                    'transaction_datetime' => now(),
                    'quantity' => $quantity,
                    'unit_cost' => $material->unit_cost,
                    'warehouse_id' => $warehouseId,
                    'reference' => $issue['reference'] ?? null,
                    'notes' => $issue['notes'] ?? null,
                    'processed_by' => $userId,
                ]);

                // Update material record
                $material->recordIssue($quantity);
            }

            $this->recalculateActualMaterialCost($workOrder);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Return materials from work order.
     */
    public function returnMaterials(WorkOrder $workOrder, array $returns, int $userId = 0): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $returns, $userId) {
            // Preload all requested materials in one query to avoid N+1
            $materialIds = array_column($returns, 'work_order_material_id');
            $materials = WorkOrderMaterial::whereIn('id', $materialIds)->get()->keyBy('id');

            foreach ($returns as $return) {
                $material = $materials->get($return['work_order_material_id'])
                    ?? throw new \InvalidArgumentException("Material {$return['work_order_material_id']} not found.");

                if ($material->work_order_id !== $workOrder->id) {
                    throw new \InvalidArgumentException('Material does not belong to this work order.');
                }

                $quantity = (float) $return['quantity'];
                $warehouseId = $return['warehouse_id'] ?? $material->warehouse_id;

                // Check if we can return this much
                if ($quantity > $material->getAvailableQuantity()) {
                    throw new \InvalidArgumentException("Cannot return more than available quantity for {$material->product->name}.");
                }

                // Record stock movement (in) - only if warehouse is set
                if ($warehouseId) {
                    $this->stockService->recordMovement(
                        productId: $material->product_id,
                        warehouseId: $warehouseId,
                        movementType: StockMovement::TYPE_MATERIAL_RETURN,
                        direction: StockMovement::DIRECTION_IN,
                        quantity: $quantity,
                        unitCost: (float) $material->unit_cost,
                        referenceType: WorkOrder::class,
                        referenceId: $workOrder->id,
                        notes: "Material return from WO: {$workOrder->work_order_number}",
                    );
                }

                // Record material transaction
                MaterialTransaction::create([
                    'organization_id' => $workOrder->organization_id,
                    'work_order_id' => $workOrder->id,
                    'work_order_material_id' => $material->id,
                    'transaction_type' => MaterialTransaction::TYPE_RETURN,
                    'transaction_datetime' => now(),
                    'quantity' => $quantity,
                    'unit_cost' => $material->unit_cost,
                    'warehouse_id' => $warehouseId,
                    'reference' => $return['reference'] ?? null,
                    'notes' => $return['notes'] ?? null,
                    'processed_by' => $userId ?: null,
                ]);

                // Update material record
                $material->recordReturn($quantity);
            }

            $this->recalculateActualMaterialCost($workOrder);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Record material consumption.
     */
    public function consumeMaterials(WorkOrder $workOrder, array $consumptions, int $userId): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $consumptions, $userId) {
            // Preload all requested materials in one query to avoid N+1
            $materialIds = array_column($consumptions, 'work_order_material_id');
            $materials = WorkOrderMaterial::whereIn('id', $materialIds)->get()->keyBy('id');

            foreach ($consumptions as $consumption) {
                $material = $materials->get($consumption['work_order_material_id'])
                    ?? throw new \InvalidArgumentException("Material {$consumption['work_order_material_id']} not found.");

                if ($material->work_order_id !== $workOrder->id) {
                    throw new \InvalidArgumentException('Material does not belong to this work order.');
                }

                $quantity = (float) $consumption['quantity'];
                $wastageQuantity = (float) ($consumption['wastage_quantity'] ?? 0);

                // Check if we have enough issued material
                $availableQuantity = $material->getAvailableQuantity();
                if (($quantity + $wastageQuantity) > $availableQuantity) {
                    throw new \InvalidArgumentException("Insufficient issued quantity for {$material->product->name}.");
                }

                // Record consumption
                $material->recordConsumption($quantity);

                // Record wastage if any
                if ($wastageQuantity > 0) {
                    $material->recordWastage($wastageQuantity);

                    MaterialTransaction::create([
                        'organization_id' => $workOrder->organization_id,
                        'work_order_id' => $workOrder->id,
                        'work_order_material_id' => $material->id,
                        'transaction_type' => MaterialTransaction::TYPE_WASTAGE,
                        'transaction_datetime' => now(),
                        'quantity' => $wastageQuantity,
                        'unit_cost' => $material->unit_cost,
                        'warehouse_id' => $material->warehouse_id,
                        'notes' => $consumption['wastage_reason'] ?? 'Material wastage',
                        'processed_by' => $userId,
                    ]);
                }
            }

            $this->recalculateActualMaterialCost($workOrder);

            return $workOrder->fresh(['materials.product']);
        });
    }

    /**
     * Record production output.
     */
    public function recordProduction(WorkOrder $workOrder, array $data, int $userId): ProductionLog
    {
        if (!$workOrder->isInProgress()) {
            throw new \InvalidArgumentException('Production can only be recorded for in-progress work orders.');
        }

        return DB::transaction(function () use ($workOrder, $data, $userId) {
            $quantityProduced = (float) $data['quantity_produced'];
            $quantityRejected = (float) ($data['quantity_rejected'] ?? 0);
            $goodQuantity = $quantityProduced - $quantityRejected;

            // Record stock movement for good quantity (only if target warehouse is set)
            if ($goodQuantity > 0 && $workOrder->target_warehouse_id) {
                $this->stockService->recordMovement(
                    productId: $workOrder->product_id,
                    warehouseId: $workOrder->target_warehouse_id,
                    movementType: StockMovement::TYPE_PRODUCTION_IN,
                    direction: StockMovement::DIRECTION_IN,
                    quantity: $goodQuantity,
                    unitCost: $workOrder->getUnitCost(),
                    referenceType: WorkOrder::class,
                    referenceId: $workOrder->id,
                    notes: "Production from WO: {$workOrder->work_order_number}",
                );
            }

            // Create production log
            $log = ProductionLog::create([
                'organization_id' => $workOrder->organization_id,
                'work_order_id' => $workOrder->id,
                'logged_at' => $data['logged_at'] ?? now(),
                'quantity_produced' => $quantityProduced,
                'quantity_rejected' => $quantityRejected,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'is_quality_checked' => false,
                'batch_number' => $data['batch_number'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'logged_by' => $userId,
            ]);

            // Update work order quantities
            $workOrder->update([
                'produced_quantity' => bcadd((string) $workOrder->produced_quantity, (string) $quantityProduced, 4),
                'rejected_quantity' => bcadd((string) $workOrder->rejected_quantity, (string) $quantityRejected, 4),
            ]);

            return $log;
        });
    }

    /**
     * Complete a work order.
     */
    public function complete(WorkOrder $workOrder): WorkOrder
    {
        if (!$workOrder->canBeCompleted()) {
            throw new \InvalidArgumentException('Work order cannot be completed in its current status.');
        }

        return DB::transaction(function () use ($workOrder) {
            // Collect all materials with remaining quantity and return in one call (avoids N+1)
            $returns = $workOrder->materials
                ->filter(fn ($material) => $material->getAvailableQuantity() > 0)
                ->map(fn ($material) => [
                    'work_order_material_id' => $material->id,
                    'quantity' => $material->getAvailableQuantity(),
                    'notes' => 'Auto-return on work order completion',
                ])
                ->values()
                ->toArray();

            if (!empty($returns)) {
                $this->returnMaterials($workOrder, $returns);
            }

            // Recalculate actual costs
            $this->recalculateActualCosts($workOrder);

            // Complete all pending operations
            foreach ($workOrder->operations()->pending()->get() as $operation) {
                $operation->skip('Auto-skipped on work order completion');
            }

            $workOrder->complete();

            // Post GL journal: FG Inventory ← WIP Inventory (only when accounts configured)
            $this->postCompletionJournal($workOrder->fresh());

            return $workOrder->fresh();
        });
    }

    /**
     * Cancel a work order.
     */
    public function cancel(WorkOrder $workOrder, string $reason): WorkOrder
    {
        if (!$workOrder->canBeCancelled()) {
            throw new \InvalidArgumentException('Work order cannot be cancelled in its current status.');
        }

        return DB::transaction(function () use ($workOrder, $reason) {
            // Collect all materials with remaining quantity and return in one call (avoids N+1)
            $returns = $workOrder->materials
                ->filter(fn ($material) => $material->getAvailableQuantity() > 0)
                ->map(fn ($material) => [
                    'work_order_material_id' => $material->id,
                    'quantity' => $material->getAvailableQuantity(),
                    'warehouse_id' => $material->warehouse_id,
                    'notes' => 'Material return due to work order cancellation',
                ])
                ->values()
                ->toArray();

            if (!empty($returns)) {
                $this->returnMaterials($workOrder, $returns);
            }

            $workOrder->cancel($reason);

            return $workOrder->fresh();
        });
    }

    /**
     * Recalculate actual material cost.
     */
    protected function recalculateActualMaterialCost(WorkOrder $workOrder): void
    {
        $totalCost = $workOrder->materials()->sum('total_cost');

        $workOrder->update(['actual_material_cost' => $totalCost]);
    }

    /**
     * Recalculate all actual costs.
     */
    protected function recalculateActualCosts(WorkOrder $workOrder): void
    {
        // Material cost
        $materialCost = $workOrder->materials()->sum('total_cost');

        // Labor cost (from operations) — eager-load to avoid N+1 on bomOperation
        $laborCost = 0;
        foreach ($workOrder->operations()->with('bomOperation')->get() as $operation) {
            if ($operation->bomOperation && $operation->actual_minutes > 0) {
                $hours = $operation->actual_minutes / 60;
                $laborCost = bcadd(
                    (string) $laborCost,
                    bcmul((string) $hours, (string) ($operation->bomOperation->labor_cost_per_hour ?? 0), 4),
                    4
                );
            }
        }

        // Overhead cost (proportional to produced quantity)
        $plannedQty = (string) $workOrder->planned_quantity;
        if (bccomp($plannedQty, '0', 4) <= 0) {
            $overheadCost = '0.0000';
        } else {
            $multiplier = bcdiv((string) $workOrder->produced_quantity, $plannedQty, 4);
            $overheadCost = bcmul((string) $workOrder->estimated_overhead_cost, $multiplier, 4);
        }

        $workOrder->update([
            'actual_material_cost' => $materialCost,
            'actual_labor_cost' => $laborCost,
            'actual_overhead_cost' => $overheadCost,
        ]);
    }

    /**
     * Post completion GL journal: Debit FG Inventory, Credit WIP Inventory.
     * Only runs when both accounts are configured in erp.default_accounts.
     */
    protected function postCompletionJournal(WorkOrder $workOrder): void
    {
        $fgAccount  = config('erp.default_accounts.fg_inventory');
        $wipAccount = config('erp.default_accounts.wip_inventory');

        if (!$fgAccount || !$wipAccount) {
            return;
        }

        $totalCost = bcadd(
            bcadd((string) $workOrder->actual_material_cost, (string) $workOrder->actual_labor_cost, 4),
            (string) $workOrder->actual_overhead_cost,
            4
        );

        if (bccomp($totalCost, '0', 4) <= 0) {
            return;
        }

        $this->journalService->create([
            'entry_date'   => now(),
            'reference'    => $workOrder->work_order_number,
            'description'  => "Work Order Completion: {$workOrder->work_order_number}",
            'source_type'  => WorkOrder::class,
            'source_id'    => $workOrder->id,
        ], [
            [
                'account_id'  => $fgAccount,
                'description' => "FG Receipt - {$workOrder->work_order_number}",
                'debit'       => $totalCost,
                'credit'      => 0,
            ],
            [
                'account_id'  => $wipAccount,
                'description' => "WIP Clearance - {$workOrder->work_order_number}",
                'debit'       => 0,
                'credit'      => $totalCost,
            ],
        ]);
    }

    /**
     * Get work order statistics.
     */
    public function getStatistics(?array $filters = []): array
    {
        $query = WorkOrder::query()
            ->where('organization_id', auth()->user()->organization_id);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->startingBetween($filters['start_date'], $filters['end_date']);
        }

        $total = $query->count();
        $draft = (clone $query)->draft()->count();
        $released = (clone $query)->released()->count();
        $inProgress = (clone $query)->inProgress()->count();
        $completed = (clone $query)->completed()->count();
        $cancelled = (clone $query)->cancelled()->count();
        $overdue = (clone $query)->overdue()->count();

        $totalPlanned = (clone $query)->sum('planned_quantity');
        $totalProduced = (clone $query)->sum('produced_quantity');
        $totalRejected = (clone $query)->sum('rejected_quantity');

        $avgCompletionRate = bccomp((string) $totalPlanned, '0', 4) > 0
            ? bcmul(bcdiv((string) $totalProduced, (string) $totalPlanned, 6), '100', 2)
            : '0.00';

        $avgRejectionRate = bccomp((string) $totalProduced, '0', 4) > 0
            ? bcmul(bcdiv((string) $totalRejected, (string) $totalProduced, 6), '100', 2)
            : '0.00';

        return [
            'total' => $total,
            'draft' => $draft,
            'released' => $released,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'overdue' => $overdue,
            'total_planned_quantity' => (float) $totalPlanned,
            'total_produced_quantity' => (float) $totalProduced,
            'total_rejected_quantity' => (float) $totalRejected,
            'avg_completion_rate' => $avgCompletionRate,
            'avg_rejection_rate' => $avgRejectionRate,
        ];
    }

    /**
     * Get production schedule for date range (capped at 90 days).
     */
    public function getProductionSchedule($startDate, $endDate): array
    {
        // Cap to 90 days to prevent unbounded loads.
        $start = \Carbon\Carbon::parse($startDate);
        $end   = \Carbon\Carbon::parse($endDate)->min($start->copy()->addDays(90));

        $workOrders = WorkOrder::active()
            ->startingBetween($start->toDateString(), $end->toDateString())
            ->with(['product:id,name,sku', 'bomTemplate:id,name', 'assignedTo:id,name'])
            ->orderBy('planned_start_date')
            ->orderBy('priority', 'desc')
            ->limit(500)
            ->get();

        return $workOrders->groupBy(fn($wo) => $wo->planned_start_date->format('Y-m-d'))
            ->map(fn($group) => $group->values())
            ->toArray();
    }
}
