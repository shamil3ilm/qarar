<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CalibrationCertificate;
use App\Models\Manufacturing\CalibrationEquipment;
use App\Models\Manufacturing\CalibrationOrder;
use App\Models\Manufacturing\CalibrationPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalibrationService
{
    /**
     * Schedule the next calibration order from a calibration plan.
     */
    public function createCalibrationOrder(CalibrationPlan $plan): CalibrationOrder
    {
        return DB::transaction(function () use ($plan): CalibrationOrder {
            $orgId = $plan->organization_id;

            // Determine scheduled date: based on last completed order or today
            $lastCompleted = CalibrationOrder::where('organization_id', $orgId)
                ->where('calibration_equipment_id', $plan->calibration_equipment_id)
                ->where('status', CalibrationOrder::STATUS_COMPLETED)
                ->orderByDesc('completed_date')
                ->first();

            $baseDate   = $lastCompleted?->completed_date ?? now()->toDateTimeImmutable();
            $nextDate   = $plan->calculateNextDueDate($baseDate);
            $orderNumber = CalibrationOrder::generateOrderNumber($orgId);

            return CalibrationOrder::create([
                'organization_id'          => $orgId,
                'calibration_equipment_id' => $plan->calibration_equipment_id,
                'calibration_plan_id'      => $plan->id,
                'order_number'             => $orderNumber,
                'scheduled_date'           => $nextDate->format('Y-m-d'),
                'status'                   => CalibrationOrder::STATUS_PLANNED,
                'external_lab'             => $plan->external_lab,
            ]);
        });
    }

    /**
     * Record calibration results, issue a certificate, and schedule the next order.
     */
    public function completeCalibration(CalibrationOrder $order, array $results): void
    {
        DB::transaction(function () use ($order, $results): void {
            $completedDate = now()->toDateString();

            // Determine next calibration date
            $nextDate = null;
            if ($order->calibration_plan_id !== null) {
                $plan     = $order->plan ?? CalibrationPlan::find($order->calibration_plan_id);
                $baseDate = \DateTimeImmutable::createFromFormat('Y-m-d', $completedDate);
                $nextDate = $plan?->calculateNextDueDate($baseDate)->format('Y-m-d');
            }

            $order->update([
                'status'                => CalibrationOrder::STATUS_COMPLETED,
                'completed_date'        => $completedDate,
                'result'                => $results['result'] ?? null,
                'actual_measurement'    => $results['actual_measurement'] ?? null,
                'notes'                 => $results['notes'] ?? $order->notes,
                'calibrated_by'         => $results['calibrated_by'] ?? $order->calibrated_by,
                'next_calibration_date' => $nextDate,
            ]);

            // Issue certificate if provided
            if (!empty($results['certificate'])) {
                $cert = $results['certificate'];
                CalibrationCertificate::create([
                    'organization_id'      => $order->organization_id,
                    'calibration_order_id' => $order->id,
                    'certificate_number'   => $cert['certificate_number'],
                    'issued_date'          => $cert['issued_date'] ?? $completedDate,
                    'valid_until'          => $cert['valid_until'] ?? $nextDate ?? $completedDate,
                    'issued_by'            => $cert['issued_by'] ?? null,
                    'accreditation_body'   => $cert['accreditation_body'] ?? null,
                    'certificate_data'     => $cert['certificate_data'] ?? null,
                ]);
            }

            // Auto-schedule next order if a plan exists
            if ($order->calibration_plan_id !== null && $nextDate !== null) {
                $plan = $order->plan ?? CalibrationPlan::find($order->calibration_plan_id);
                if ($plan?->is_active) {
                    $this->createCalibrationOrder($plan);
                }
            }
        });
    }

    /**
     * Get all equipment with overdue calibration for an organization.
     *
     * Uses a single JOIN + DISTINCT instead of loading all orders and
     * de-duplicating equipment in PHP memory.
     */
    public function getOverdueEquipment(int $organizationId): Collection
    {
        $equipmentIds = CalibrationOrder::where('organization_id', $organizationId)
            ->whereIn('status', [CalibrationOrder::STATUS_PLANNED, CalibrationOrder::STATUS_OVERDUE])
            ->where('scheduled_date', '<', now()->toDateString())
            ->distinct()
            ->pluck('equipment_id');

        if ($equipmentIds->isEmpty()) {
            return collect();
        }

        return CalibrationEquipment::whereIn('id', $equipmentIds)->get();
    }

    /**
     * Get upcoming calibration orders within a given number of days.
     */
    public function getUpcomingCalibrations(int $organizationId, int $days = 30): Collection
    {
        return CalibrationOrder::with(['equipment', 'plan'])
            ->where('organization_id', $organizationId)
            ->where('status', CalibrationOrder::STATUS_PLANNED)
            ->whereBetween('scheduled_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Check whether a piece of equipment has a valid, completed calibration.
     */
    public function isEquipmentCalibrated(int $equipmentId): bool
    {
        $lastCompleted = CalibrationOrder::where('calibration_equipment_id', $equipmentId)
            ->where('status', CalibrationOrder::STATUS_COMPLETED)
            ->where('result', CalibrationOrder::RESULT_PASS)
            ->whereNotNull('next_calibration_date')
            ->where('next_calibration_date', '>=', now()->toDateString())
            ->exists();

        return $lastCompleted;
    }

    /**
     * Auto-generate due calibration orders for all active plans in an organization.
     *
     * @return int Number of orders generated.
     */
    public function generateCalibrationOrders(int $organizationId): int
    {
        $plans = CalibrationPlan::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('equipment')
            ->get();

        $generated = 0;

        foreach ($plans as $plan) {
            // Skip if a pending/in-progress order already exists for this plan
            $exists = CalibrationOrder::where('calibration_plan_id', $plan->id)
                ->whereIn('status', [CalibrationOrder::STATUS_PLANNED, CalibrationOrder::STATUS_IN_PROGRESS])
                ->exists();

            if ($exists) {
                continue;
            }

            // Check whether the last completed order's next_calibration_date has arrived
            $lastCompleted = CalibrationOrder::where('calibration_plan_id', $plan->id)
                ->where('status', CalibrationOrder::STATUS_COMPLETED)
                ->orderByDesc('completed_date')
                ->first();

            $shouldGenerate = $lastCompleted === null
                || ($lastCompleted->next_calibration_date !== null
                    && $lastCompleted->next_calibration_date <= now()->addDays(7)->toDateString());

            if ($shouldGenerate) {
                $this->createCalibrationOrder($plan);
                $generated++;
            }
        }

        return $generated;
    }
}
