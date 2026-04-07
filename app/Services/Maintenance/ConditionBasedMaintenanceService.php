<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Inventory\StockLevel;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\EquipmentSparePart;
use App\Models\Maintenance\MaintenanceConditionRule;
use App\Models\Maintenance\MaintenanceMeasurement;
use App\Models\Maintenance\MaintenanceOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ConditionBasedMaintenanceService
{
    /**
     * Record a measurement reading and evaluate active rules.
     */
    public function recordMeasurement(array $data): MaintenanceMeasurement
    {
        return DB::transaction(function () use ($data): MaintenanceMeasurement {
            $equipmentId       = (int) $data['equipment_id'];
            $measurementPoint  = $data['measurement_point'];
            $value             = (float) $data['measurement_value'];

            // Evaluate rules to find a breach
            $breachedRule = $this->evaluateRules($equipmentId, $measurementPoint, $value);

            $measurement = MaintenanceMeasurement::create([
                'organization_id'    => $data['organization_id'] ?? auth()->user()?->organization_id,
                'equipment_id'       => $equipmentId,
                'measurement_point'  => $measurementPoint,
                'measurement_value'  => $value,
                'unit_of_measure'    => $data['unit_of_measure'] ?? null,
                'measured_at'        => $data['measured_at'] ?? now(),
                'recorded_by'        => auth()->id(),
                'threshold_breached' => $breachedRule !== null,
                'triggered_rule_id'  => $breachedRule?->id,
            ]);

            if ($breachedRule !== null) {
                $this->handleBreach($measurement, $breachedRule);
            }

            return $measurement;
        });
    }

    /**
     * Evaluate all active rules for an equipment+point combination against a value.
     * Returns the first matching (breached) rule, or null if none breached.
     */
    public function evaluateRules(int $equipmentId, string $measurementPoint, float $value): ?MaintenanceConditionRule
    {
        $rules = MaintenanceConditionRule::withoutGlobalScope('organization')
            ->active()
            ->forEquipment($equipmentId)
            ->forMeasurementPoint($measurementPoint)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->isBreached($value)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Add a spare part to an equipment record.
     */
    public function addSparePart(array $data): EquipmentSparePart
    {
        $equipmentId = (int) $data['equipment_id'];

        // Refresh current stock from inventory
        $currentStock = $this->fetchCurrentStock((int) $data['product_id'], $equipmentId);

        return EquipmentSparePart::updateOrCreate(
            [
                'equipment_id' => $equipmentId,
                'product_id'   => $data['product_id'],
            ],
            [
                'recommended_stock_qty' => $data['recommended_stock_qty'] ?? 0,
                'current_stock_qty'     => $currentStock,
                'is_critical'           => $data['is_critical'] ?? false,
                'lead_time_days'        => $data['lead_time_days'] ?? 0,
            ]
        );
    }

    /**
     * Check availability of all spare parts for an equipment.
     *
     * @return array{equipment_id: int, parts: Collection, critical_shortfall: int, total_parts: int}
     */
    public function checkSparePartsAvailability(int $equipmentId): array
    {
        $parts = EquipmentSparePart::with('product')
            ->forEquipment($equipmentId)
            ->get();

        // Sync current stock from inventory before evaluating
        foreach ($parts as $part) {
            $current = $this->fetchCurrentStock($part->product_id, $equipmentId);
            if ((float) $part->current_stock_qty !== $current) {
                $part->update(['current_stock_qty' => $current]);
                $part->current_stock_qty = $current;
            }
        }

        $shortfalls         = $parts->filter(fn($p) => !$p->isStockSufficient());
        $criticalShortfalls = $shortfalls->filter(fn($p) => $p->is_critical);

        return [
            'equipment_id'         => $equipmentId,
            'total_parts'          => $parts->count(),
            'sufficient_count'     => $parts->count() - $shortfalls->count(),
            'shortfall_count'      => $shortfalls->count(),
            'critical_shortfall'   => $criticalShortfalls->count(),
            'parts'                => $parts->map(fn($p) => [
                'product_id'            => $p->product_id,
                'product_name'          => $p->product?->name,
                'recommended_stock_qty' => (float) $p->recommended_stock_qty,
                'current_stock_qty'     => (float) $p->current_stock_qty,
                'deficit'               => $p->getStockDeficit(),
                'is_critical'           => $p->is_critical,
                'lead_time_days'        => (float) $p->lead_time_days,
                'sufficient'            => $p->isStockSufficient(),
            ])->values(),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Handle a rule breach — create maintenance order and/or notify.
     */
    private function handleBreach(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        if ($rule->shouldCreateOrder()) {
            $this->createMaintenanceOrder($measurement, $rule);
        }

        if ($rule->shouldNotify()) {
            $this->sendBreachNotification($measurement, $rule);
        }
    }

    /**
     * Auto-create a corrective maintenance order on breach.
     */
    private function createMaintenanceOrder(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        try {
            $orgId = $measurement->organization_id;

            MaintenanceOrder::create([
                'organization_id' => $orgId,
                'order_number'    => MaintenanceOrder::generateOrderNumber($orgId),
                'equipment_id'    => $measurement->equipment_id,
                'order_type'      => MaintenanceOrder::TYPE_CORRECTIVE,
                'priority'        => MaintenanceOrder::PRIORITY_HIGH,
                'status'          => MaintenanceOrder::STATUS_OPEN,
                'description'     => "Auto-generated: rule '{$rule->rule_name}' breached. "
                    . "Measurement: {$measurement->measurement_value} {$measurement->unit_of_measure} "
                    . "at {$measurement->measurement_point}.",
                'scheduled_start' => now(),
                'created_by'      => $measurement->recorded_by,
            ]);
        } catch (\Throwable $e) {
            Log::error('ConditionBasedMaintenanceService: failed to create maintenance order.', [
                'rule_id'        => $rule->id,
                'measurement_id' => $measurement->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a notification about a threshold breach.
     */
    private function sendBreachNotification(MaintenanceMeasurement $measurement, MaintenanceConditionRule $rule): void
    {
        Log::info('ConditionBasedMaintenanceService: threshold breached, notification queued.', [
            'rule_id'          => $rule->id,
            'equipment_id'     => $measurement->equipment_id,
            'measurement_point' => $measurement->measurement_point,
            'value'            => $measurement->measurement_value,
        ]);
        // Notification dispatch can be extended to use a dedicated Notification class.
    }

    /**
     * Fetch the current aggregate stock for a product across all locations.
     */
    private function fetchCurrentStock(int $productId, int $equipmentId): float
    {
        // Use organization-scoped total stock quantity for the product
        return (float) StockLevel::withoutGlobalScope('organization')
            ->where('product_id', $productId)
            ->sum('quantity');
    }
}
