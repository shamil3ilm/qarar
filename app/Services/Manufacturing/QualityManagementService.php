<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapaRecord;
use App\Models\Manufacturing\DefectRecord;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\InspectionResult;
use App\Models\Manufacturing\QualityNotification;
use App\Models\Manufacturing\QualityPlan;
use App\Models\Manufacturing\QualityPlanCharacteristic;
use App\Models\Purchase\PurchaseOrder;
use App\Services\Core\NumberGeneratorService;
use App\Services\Inventory\StockService;
use Illuminate\Support\Facades\DB;

class QualityManagementService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private StockService $stockService,
    ) {}

    /**
     * Create a quality plan together with its characteristics in a single transaction.
     */
    public function createQualityPlan(array $data, array $characteristics, int $userId): QualityPlan
    {
        return DB::transaction(function () use ($data, $characteristics, $userId) {
            $plan = QualityPlan::create([
                'organization_id' => auth()->user()->organization_id,
                'name' => $data['name'],
                'product_id' => $data['product_id'] ?? null,
                'product_category_id' => $data['product_category_id'] ?? null,
                'inspection_stage' => $data['inspection_stage'] ?? QualityPlan::STAGE_GOODS_RECEIPT,
                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($characteristics as $index => $char) {
                QualityPlanCharacteristic::create([
                    'quality_plan_id' => $plan->id,
                    'name' => $char['name'],
                    'description' => $char['description'] ?? null,
                    'inspection_method' => $char['inspection_method'] ?? null,
                    'measurement_unit' => $char['measurement_unit'] ?? null,
                    'lower_limit' => $char['lower_limit'] ?? null,
                    'upper_limit' => $char['upper_limit'] ?? null,
                    'target_value' => $char['target_value'] ?? null,
                    'is_mandatory' => $char['is_mandatory'] ?? true,
                    'sort_order' => $char['sort_order'] ?? $index,
                ]);
            }

            return $plan->fresh(['characteristics', 'product']);
        });
    }

    /**
     * Create a new inspection lot, auto-generating the lot number.
     */
    public function createInspectionLot(array $data, int $userId): InspectionLot
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId = auth()->user()->organization_id;
            $lotNumber = $this->generateLotNumber($orgId);

            $lot = InspectionLot::create([
                'organization_id' => $orgId,
                'lot_number' => $lotNumber,
                'quality_plan_id' => $data['quality_plan_id'] ?? null,
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'source_type' => $data['source_type'] ?? InspectionLot::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'quantity' => $data['quantity'],
                'inspected_quantity' => 0,
                'accepted_quantity' => 0,
                'rejected_quantity' => 0,
                'status' => InspectionLot::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            return $lot->fresh(['qualityPlan', 'product', 'warehouse']);
        });
    }

    /**
     * Record one or more inspection results against an inspection lot.
     *
     * Each result entry may include:
     *   - quality_plan_characteristic_id (nullable)
     *   - characteristic_name
     *   - measured_value (nullable)
     *   - text_result (nullable)
     *   - is_conforming (nullable bool — derived automatically when a
     *     characteristic with numeric limits is linked)
     *   - notes
     */
    public function recordInspectionResults(
        InspectionLot $lot,
        array $results,
        int $userId
    ): InspectionLot {
        return DB::transaction(function () use ($lot, $results, $userId) {
            if ($lot->isPending()) {
                $lot->startInspection();
                $lot->refresh();
            }

            // Pre-load characteristics referenced in the results to avoid N+1
            $characteristicIds = array_filter(array_column($results, 'quality_plan_characteristic_id'));
            $characteristics = QualityPlanCharacteristic::whereIn('id', $characteristicIds)
                ->get()
                ->keyBy('id');

            foreach ($results as $result) {
                $charId = $result['quality_plan_characteristic_id'] ?? null;
                $characteristic = $charId ? $characteristics->get($charId) : null;

                // Auto-derive conformance from numeric limits when available
                $isConforming = $result['is_conforming'] ?? null;
                if (
                    $isConforming === null
                    && $characteristic !== null
                    && isset($result['measured_value'])
                    && $characteristic->hasNumericLimits()
                ) {
                    $isConforming = $characteristic->isWithinLimits((float) $result['measured_value']);
                }

                InspectionResult::create([
                    'inspection_lot_id' => $lot->id,
                    'quality_plan_characteristic_id' => $charId,
                    'characteristic_name' => $result['characteristic_name']
                        ?? ($characteristic?->name ?? ''),
                    'measured_value' => $result['measured_value'] ?? null,
                    'text_result' => $result['text_result'] ?? null,
                    'is_conforming' => $isConforming,
                    'notes' => $result['notes'] ?? null,
                    'recorded_by' => $userId,
                ]);
            }

            return $lot->fresh(['results', 'qualityPlan']);
        });
    }

    /**
     * Finalise an inspection lot with accepted and rejected quantities.
     */
    public function completeInspection(
        InspectionLot $lot,
        float $accepted,
        float $rejected,
        int $userId
    ): InspectionLot {
        return DB::transaction(function () use ($lot, $accepted, $rejected, $userId) {
            if (!in_array($lot->status, [
                InspectionLot::STATUS_PENDING,
                InspectionLot::STATUS_IN_INSPECTION,
            ], true)) {
                throw new \InvalidArgumentException(
                    'Inspection lot cannot be completed in its current status.'
                );
            }

            $total = (float) $lot->quantity;

            if (($accepted + $rejected) > $total) {
                throw new \InvalidArgumentException(
                    'Accepted + rejected quantities cannot exceed the lot quantity.'
                );
            }

            $completedLot = $lot->complete($accepted, $rejected, $userId);

            // Post stock movements when the lot is linked to a warehouse (usage decision).
            // Accepted qty → unrestricted stock (QM movement 321).
            // Rejected qty → blocked/scrap stock (QM movement 344).
            if ($completedLot->warehouse_id !== null) {
                if ($accepted > 0) {
                    try {
                        $this->stockService->recordMovement(
                            productId: $completedLot->product_id,
                            warehouseId: $completedLot->warehouse_id,
                            movementType: '321',
                            direction: 'IN',
                            quantity: $accepted,
                            unitCost: 0,
                            referenceType: 'inspection_lot',
                            referenceId: $completedLot->id,
                            referenceNumber: $completedLot->lot_number,
                            notes: "QM usage decision — accepted qty from lot {$completedLot->lot_number}",
                            createdBy: $userId,
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('QM stock posting failed (accepted qty)', [
                            'lot_number' => $completedLot->lot_number,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }

                if ($rejected > 0) {
                    try {
                        $this->stockService->recordMovement(
                            productId: $completedLot->product_id,
                            warehouseId: $completedLot->warehouse_id,
                            movementType: '344',
                            direction: 'OUT',
                            quantity: $rejected,
                            unitCost: 0,
                            referenceType: 'inspection_lot',
                            referenceId: $completedLot->id,
                            referenceNumber: $completedLot->lot_number,
                            notes: "QM usage decision — rejected qty from lot {$completedLot->lot_number}",
                            createdBy: $userId,
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('QM stock posting failed (rejected qty)', [
                            'lot_number' => $completedLot->lot_number,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Auto-create a CAPA record whenever rejected quantity > 0.
            if ($rejected > 0) {
                try {
                    CapaRecord::create([
                        'organization_id'   => $completedLot->organization_id,
                        'capa_number'       => $this->numberGenerator->generate('CAPA'),
                        'capa_type'         => 'corrective',
                        'source_type'       => 'inspection_lot',
                        'source_id'         => $completedLot->id,
                        'problem_statement' => "Rejected quantity {$rejected} on inspection lot {$completedLot->lot_number}.",
                        'priority'          => 'high',
                        'status'            => 'open',
                        'owner_id'          => $userId,
                        'target_close_date' => now()->addDays(30),
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('CAPA auto-creation failed for lot rejection', [
                        'lot_number' => $completedLot->lot_number,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            return $completedLot;
        });
    }

    /**
     * Create a quality notification, auto-generating the notification number.
     */
    public function createNotification(array $data, int $userId): QualityNotification
    {
        return DB::transaction(function () use ($data, $userId) {
            $orgId = auth()->user()->organization_id;
            $notificationNumber = $this->generateNotificationNumber($orgId);

            $notification = QualityNotification::create([
                'organization_id' => $orgId,
                'notification_number' => $notificationNumber,
                'notification_type' => $data['notification_type'] ?? QualityNotification::TYPE_DEFECT,
                'source_type' => $data['source_type'] ?? QualityNotification::SOURCE_INTERNAL,
                'source_id' => $data['source_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? QualityNotification::PRIORITY_MEDIUM,
                'status' => QualityNotification::STATUS_OPEN,
                'assigned_to' => $data['assigned_to'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $userId,
            ]);

            // Persist any initial defect records if provided
            if (!empty($data['defects'])) {
                foreach ($data['defects'] as $defect) {
                    DefectRecord::create([
                        'quality_notification_id' => $notification->id,
                        'defect_type' => $defect['defect_type'],
                        'defect_code' => $defect['defect_code'] ?? null,
                        'quantity' => $defect['quantity'] ?? 1,
                        'severity' => $defect['severity'] ?? DefectRecord::SEVERITY_MINOR,
                        'description' => $defect['description'] ?? null,
                        'location' => $defect['location'] ?? null,
                    ]);
                }
            }

            return $notification->fresh(['defects', 'assignee', 'product']);
        });
    }

    /**
     * Assign a notification to a user and transition to in_progress status.
     */
    public function assignNotification(
        QualityNotification $notification,
        int $assigneeId,
        int $userId
    ): QualityNotification {
        return DB::transaction(function () use ($notification, $assigneeId, $userId) {
            $notification->update([
                'assigned_to' => $assigneeId,
                'assigned_by' => $userId,
                'assigned_at' => now(),
                'status' => QualityNotification::STATUS_IN_PROGRESS,
            ]);

            return $notification->fresh(['assignee']);
        });
    }

    /**
     * Resolve a quality notification with root cause and corrective action.
     */
    public function resolveNotification(
        QualityNotification $notification,
        array $data,
        int $userId
    ): QualityNotification {
        return DB::transaction(function () use ($notification, $data, $userId) {
            if (!$notification->canBeResolved()) {
                throw new \InvalidArgumentException(
                    'Notification cannot be resolved in its current status.'
                );
            }

            $notification->update([
                'status' => QualityNotification::STATUS_RESOLVED,
                'root_cause' => $data['root_cause'],
                'corrective_action' => $data['corrective_action'],
                'preventive_action' => $data['preventive_action'] ?? null,
                'resolved_at' => now(),
                'resolved_by' => $userId,
            ]);

            return $notification->fresh();
        });
    }

    /**
     * Close a resolved notification.
     */
    public function closeNotification(
        QualityNotification $notification,
        int $userId
    ): QualityNotification {
        return DB::transaction(function () use ($notification, $userId) {
            if (!$notification->canBeClosed()) {
                throw new \InvalidArgumentException(
                    'Only resolved notifications can be closed.'
                );
            }

            return $notification->close($userId);
        });
    }

    /**
     * Return aggregate quality statistics for the given organisation and date range.
     *
     * Returned keys:
     *   total_lots, accepted_lots, rejected_lots, partial_lots, pending_lots,
     *   avg_acceptance_rate, open_notifications_by_priority, defects_by_type
     */
    public function getQualityStats(int $orgId, string $from, string $to): array
    {
        $lotQuery = InspectionLot::where('organization_id', $orgId)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        $totalLots = (clone $lotQuery)->count();
        $acceptedLots = (clone $lotQuery)->where('status', InspectionLot::STATUS_ACCEPTED)->count();
        $rejectedLots = (clone $lotQuery)->where('status', InspectionLot::STATUS_REJECTED)->count();
        $partialLots = (clone $lotQuery)->where('status', InspectionLot::STATUS_PARTIAL_ACCEPT)->count();
        $pendingLots = (clone $lotQuery)->whereIn('status', [
            InspectionLot::STATUS_PENDING,
            InspectionLot::STATUS_IN_INSPECTION,
        ])->count();

        $inspectedTotal = (clone $lotQuery)->sum('inspected_quantity');
        $acceptedTotal = (clone $lotQuery)->sum('accepted_quantity');
        $avgAcceptanceRate = $inspectedTotal > 0
            ? round(($acceptedTotal / $inspectedTotal) * 100, 2)
            : 0.0;

        // Open notifications grouped by priority
        $openByPriority = QualityNotification::where('organization_id', $orgId)
            ->whereIn('status', [
                QualityNotification::STATUS_OPEN,
                QualityNotification::STATUS_IN_PROGRESS,
            ])
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        // Defect count grouped by type within date range
        $defectsByType = DefectRecord::join(
            'quality_notifications',
            'quality_notifications.id',
            '=',
            'defect_records.quality_notification_id'
        )
            ->where('quality_notifications.organization_id', $orgId)
            ->whereBetween('defect_records.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNull('quality_notifications.deleted_at')
            ->selectRaw('defect_records.defect_type, sum(defect_records.quantity) as total')
            ->groupBy('defect_records.defect_type')
            ->pluck('total', 'defect_type')
            ->toArray();

        return [
            'total_lots' => $totalLots,
            'accepted_lots' => $acceptedLots,
            'rejected_lots' => $rejectedLots,
            'partial_lots' => $partialLots,
            'pending_lots' => $pendingLots,
            'avg_acceptance_rate' => $avgAcceptanceRate,
            'open_notifications_by_priority' => $openByPriority,
            'defects_by_type' => $defectsByType,
        ];
    }

    /**
     * Create an inspection lot automatically from a purchase order, linking the
     * most applicable active quality plan for the ordered product.
     */
    public function createInspectionLotFromPurchaseOrder(int $poId, int $userId): InspectionLot
    {
        return DB::transaction(function () use ($poId, $userId) {
            $po = PurchaseOrder::with('lines.product')->findOrFail($poId);

            // Use the first line's product for the inspection lot
            $firstLine = $po->lines->first();

            if ($firstLine === null) {
                throw new \InvalidArgumentException(
                    'Purchase order has no lines; cannot create inspection lot.'
                );
            }

            $productId = $firstLine->product_id;
            $orgId = auth()->user()->organization_id;

            // Find the most specific active quality plan for this product at goods receipt stage
            $qualityPlan = QualityPlan::where('organization_id', $orgId)
                ->where('is_active', true)
                ->where('inspection_stage', QualityPlan::STAGE_GOODS_RECEIPT)
                ->where(function ($query) use ($productId) {
                    $query->where('product_id', $productId)
                        ->orWhereNull('product_id');
                })
                ->orderByRaw('CASE WHEN product_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();

            $totalQuantity = $po->lines->sum('quantity');

            $lotNumber = $this->generateLotNumber($orgId);

            $lot = InspectionLot::create([
                'organization_id' => $orgId,
                'lot_number' => $lotNumber,
                'quality_plan_id' => $qualityPlan?->id,
                'product_id' => $productId,
                'warehouse_id' => null,
                'source_type' => InspectionLot::SOURCE_PURCHASE_ORDER,
                'source_id' => $po->id,
                'quantity' => $totalQuantity,
                'inspected_quantity' => 0,
                'accepted_quantity' => 0,
                'rejected_quantity' => 0,
                'status' => InspectionLot::STATUS_PENDING,
                'notes' => "Auto-created from PO: {$po->order_number}",
                'created_by' => $userId,
            ]);

            return $lot->fresh(['qualityPlan', 'product']);
        });
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Generate a sequential lot number in the format LOT-YYYY-000001.
     */
    private function generateLotNumber(int $orgId): string
    {
        return $this->numberGenerator->generate('LOT', '{prefix}-{year}-{number}', $orgId);
    }

    /**
     * Generate a sequential quality notification number in the format QN-YYYY-000001.
     */
    private function generateNotificationNumber(int $orgId): string
    {
        return $this->numberGenerator->generate('QN', '{prefix}-{year}-{number}', $orgId);
    }
}
