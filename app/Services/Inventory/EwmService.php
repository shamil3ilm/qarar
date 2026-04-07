<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\EwmBin;
use App\Models\Inventory\EwmLaborTask;
use App\Models\Inventory\EwmPutawayRule;
use App\Models\Inventory\EwmStorageSection;
use App\Models\Inventory\EwmStorageType;
use App\Models\Inventory\EwmTransferOrder;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EwmService
{
    public function __construct(private NumberGeneratorService $numberGen) {}

    // -------------------------------------------------------------------------
    // Storage Types
    // -------------------------------------------------------------------------

    public function createStorageType(int $organizationId, array $data): EwmStorageType
    {
        return EwmStorageType::create([
            ...$data,
            'organization_id' => $organizationId,
        ]);
    }

    public function listStorageTypes(int $organizationId, int $warehouseId): \Illuminate\Database\Eloquent\Collection
    {
        return EwmStorageType::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Bins
    // -------------------------------------------------------------------------

    public function createBin(int $organizationId, array $data): EwmBin
    {
        return EwmBin::create([
            ...$data,
            'organization_id' => $organizationId,
        ]);
    }

    public function listBins(int $organizationId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = EwmBin::where('organization_id', $organizationId)
            ->with(['storageType', 'storageSection'])
            ->orderBy('bin_code');

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['storage_type_id'])) {
            $query->where('storage_type_id', $filters['storage_type_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['aisle'])) {
            $query->where('aisle', $filters['aisle']);
        }

        return $query->paginate($perPage);
    }

    public function updateBinStatus(int $organizationId, string $uuid, string $status): EwmBin
    {
        $bin = EwmBin::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $bin->update(['status' => $status]);

        return $bin->fresh(['storageType', 'storageSection']);
    }

    /**
     * Find the best putaway bin based on strategy (SAP EWM L09A).
     */
    public function findPutawayBin(int $organizationId, int $warehouseId, int $productId, float $qty): ?EwmBin
    {
        // Get applicable putaway rule (product-specific first, then category, then default)
        $rule = EwmPutawayRule::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)->orWhereNull('product_id');
            })
            ->orderBy('priority')
            ->first();

        $strategy = $rule?->strategy ?? EwmPutawayRule::STRATEGY_FIFO;

        $binQuery = EwmBin::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', EwmBin::STATUS_ACTIVE)
            ->where('fill_pct', '<', 100);

        return match ($strategy) {
            EwmPutawayRule::STRATEGY_NEAREST_BIN => $binQuery->orderBy('fill_pct', 'desc')->first(),
            EwmPutawayRule::STRATEGY_MAX_FILL    => $binQuery->orderBy('fill_pct', 'desc')->first(),
            EwmPutawayRule::STRATEGY_FIXED_BIN   => EwmBin::where('organization_id', $organizationId)
                ->where('warehouse_id', $warehouseId)
                ->where('bin_code', $rule?->fixed_bin_code)
                ->where('status', EwmBin::STATUS_ACTIVE)
                ->first(),
            default => $binQuery->orderBy('fill_pct')->first(),  // fifo, fefo, lifo — least full first
        };
    }

    // -------------------------------------------------------------------------
    // Transfer Orders
    // -------------------------------------------------------------------------

    /**
     * Create a transfer order (putaway, pick, internal move).
     */
    public function createTransferOrder(int $organizationId, array $data, int $userId): EwmTransferOrder
    {
        return DB::transaction(function () use ($organizationId, $data, $userId) {
            $toNumber = $this->numberGen->generate('TO', null, $organizationId);

            $to = EwmTransferOrder::create([
                ...$data,
                'organization_id' => $organizationId,
                'to_number'       => $toNumber,
                'status'          => EwmTransferOrder::STATUS_CREATED,
                'created_by'      => $userId,
            ]);

            // Auto-create labor task
            EwmLaborTask::create([
                'organization_id'   => $organizationId,
                'warehouse_id'      => $data['warehouse_id'],
                'transfer_order_id' => $to->id,
                'task_type'         => $this->movementToTaskType($data['movement_type']),
                'priority'          => $data['priority'] ?? EwmLaborTask::PRIORITY_NORMAL,
                'status'            => EwmLaborTask::STATUS_QUEUED,
            ]);

            return $to->load(['sourceBin', 'destBin', 'product']);
        });
    }

    /**
     * Confirm transfer order execution and update bin fill levels.
     */
    public function confirmTransferOrder(int $organizationId, string $uuid, float $confirmedQty, int $userId): EwmTransferOrder
    {
        return DB::transaction(function () use ($organizationId, $uuid, $confirmedQty, $userId) {
            $to = EwmTransferOrder::where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            $durationMinutes = $to->started_at
                ? (float) now()->diffInMinutes($to->started_at)
                : null;

            $to->update([
                'status'                   => EwmTransferOrder::STATUS_CONFIRMED,
                'confirmed_qty'            => $confirmedQty,
                'confirmed_at'             => now(),
                'actual_duration_minutes'  => $durationMinutes,
            ]);

            // Update bin fill percentages
            if ($to->source_bin_id) {
                $this->adjustBinFill($to->source_bin_id, -$confirmedQty);
            }

            if ($to->dest_bin_id) {
                $this->adjustBinFill($to->dest_bin_id, $confirmedQty);
            }

            // Complete the associated labor task
            EwmLaborTask::where('transfer_order_id', $to->id)
                ->whereNotIn('status', [EwmLaborTask::STATUS_COMPLETED, EwmLaborTask::STATUS_CANCELLED])
                ->update([
                    'status'        => EwmLaborTask::STATUS_COMPLETED,
                    'completed_at'  => now(),
                    'actual_minutes' => $durationMinutes,
                ]);

            return $to->fresh(['sourceBin', 'destBin', 'product']);
        });
    }

    /**
     * Cancel a transfer order that has not yet been confirmed.
     */
    public function cancelTransferOrder(int $organizationId, string $uuid, int $userId): EwmTransferOrder
    {
        return DB::transaction(function () use ($organizationId, $uuid, $userId) {
            $to = EwmTransferOrder::where('organization_id', $organizationId)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->firstOrFail();

            $to->update(['status' => EwmTransferOrder::STATUS_CANCELLED]);

            EwmLaborTask::where('transfer_order_id', $to->id)
                ->whereNotIn('status', [EwmLaborTask::STATUS_COMPLETED, EwmLaborTask::STATUS_CANCELLED])
                ->update(['status' => EwmLaborTask::STATUS_CANCELLED]);

            return $to->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Putaway Rules
    // -------------------------------------------------------------------------

    public function createPutawayRule(int $organizationId, array $data): EwmPutawayRule
    {
        return EwmPutawayRule::create([
            ...$data,
            'organization_id' => $organizationId,
        ]);
    }

    public function listPutawayRules(int $organizationId, int $warehouseId): \Illuminate\Database\Eloquent\Collection
    {
        return EwmPutawayRule::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->with(['storageType', 'product', 'category'])
            ->orderBy('priority')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Labor Management
    // -------------------------------------------------------------------------

    /**
     * Assign a labor task to a user.
     */
    public function assignTask(int $organizationId, string $uuid, int $assignedTo): EwmLaborTask
    {
        $task = EwmLaborTask::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $task->update([
            'status'      => EwmLaborTask::STATUS_ASSIGNED,
            'assigned_to' => $assignedTo,
            'assigned_at' => now(),
        ]);

        // Also update transfer order if linked
        if ($task->transfer_order_id) {
            EwmTransferOrder::where('id', $task->transfer_order_id)
                ->update([
                    'status'      => EwmTransferOrder::STATUS_ASSIGNED,
                    'assigned_to' => $assignedTo,
                    'assigned_at' => now(),
                ]);
        }

        return $task->fresh(['assignedTo', 'transferOrder']);
    }

    /**
     * Mark a labor task as started.
     */
    public function startTask(int $organizationId, string $uuid): EwmLaborTask
    {
        $task = EwmLaborTask::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $task->update([
            'status'     => EwmLaborTask::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        if ($task->transfer_order_id) {
            EwmTransferOrder::where('id', $task->transfer_order_id)
                ->update([
                    'status'     => EwmTransferOrder::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ]);
        }

        return $task->fresh();
    }

    /**
     * Get labor productivity dashboard.
     * Uses DB-level aggregations throughout — no full collection loaded into memory.
     */
    public function getLaborDashboard(int $organizationId, int $warehouseId, int $days = 7): array
    {
        $since = now()->subDays($days);

        $base = EwmLaborTask::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('created_at', '>=', $since);

        // Status counts via a single GROUP BY query
        $statusCounts = (clone $base)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Task type breakdown via a single GROUP BY query
        $byTaskType = (clone $base)
            ->selectRaw('task_type, COUNT(*) as cnt')
            ->groupBy('task_type')
            ->pluck('cnt', 'task_type');

        // Per-user productivity (already aggregated)
        $byUser = (clone $base)
            ->where('status', EwmLaborTask::STATUS_COMPLETED)
            ->selectRaw('assigned_to, COUNT(*) as tasks_completed, AVG(actual_minutes) as avg_minutes')
            ->groupBy('assigned_to')
            ->with('assignedTo:id,name')
            ->get();

        return [
            'period_days'  => $days,
            'total_tasks'  => (int) $statusCounts->sum(),
            'completed'    => (int) ($statusCounts[EwmLaborTask::STATUS_COMPLETED]  ?? 0),
            'in_progress'  => (int) ($statusCounts[EwmLaborTask::STATUS_IN_PROGRESS] ?? 0),
            'queued'       => (int) ($statusCounts[EwmLaborTask::STATUS_QUEUED]      ?? 0),
            'by_task_type' => $byTaskType,
            'productivity' => $byUser,
        ];
    }

    // -------------------------------------------------------------------------
    // Reports
    // -------------------------------------------------------------------------

    /**
     * Get bin utilization report.
     */
    public function getBinUtilization(int $organizationId, int $warehouseId): array
    {
        $bins = EwmBin::where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw(
                'status, COUNT(*) as count, AVG(fill_pct) as avg_fill, '
                . 'SUM(CASE WHEN fill_pct = 0 THEN 1 ELSE 0 END) as empty_bins, '
                . 'SUM(CASE WHEN fill_pct >= 90 THEN 1 ELSE 0 END) as near_full_bins'
            )
            ->groupBy('status')
            ->get();

        return [
            'warehouse_id' => $warehouseId,
            'utilization'  => $bins,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function adjustBinFill(int $binId, float $qtyDelta): void
    {
        $bin = EwmBin::lockForUpdate()->find($binId);
        if ($bin === null) {
            return;
        }

        $newWeight = max(0, $bin->current_weight_kg + $qtyDelta);
        $bin->current_weight_kg = $newWeight;

        if ($bin->max_weight_kg > 0) {
            $bin->fill_pct = min(100, ($newWeight / $bin->max_weight_kg) * 100);
        }

        $bin->save();
    }

    private function movementToTaskType(string $movementType): string
    {
        return match ($movementType) {
            EwmTransferOrder::MOVEMENT_GOODS_RECEIPT  => EwmLaborTask::TASK_TYPE_PUT,
            EwmTransferOrder::MOVEMENT_GOODS_ISSUE    => EwmLaborTask::TASK_TYPE_PICK,
            EwmTransferOrder::MOVEMENT_REPLENISHMENT  => EwmLaborTask::TASK_TYPE_MOVE,
            default                                   => EwmLaborTask::TASK_TYPE_MOVE,
        };
    }
}
