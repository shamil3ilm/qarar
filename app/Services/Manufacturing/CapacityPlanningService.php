<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapacityLoad;
use App\Models\Manufacturing\CapacityRequirement;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterException;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CapacityPlanningService
{
    /**
     * Create a new work center.
     */
    public function createWorkCenter(array $data, int $userId): WorkCenter
    {
        return DB::transaction(function () use ($data, $userId): WorkCenter {
            return WorkCenter::create([
                'organization_id'   => $data['organization_id'],
                'code'              => $data['code'],
                'name'              => $data['name'],
                'description'       => $data['description'] ?? null,
                'work_center_type'  => $data['work_center_type'] ?? WorkCenter::TYPE_MACHINE,
                'capacity_per_day'  => $data['capacity_per_day'] ?? 8,
                'efficiency_percent' => $data['efficiency_percent'] ?? 100,
                'calendar_type'     => $data['calendar_type'] ?? WorkCenter::CALENDAR_5DAY,
                'cost_per_hour'     => $data['cost_per_hour'] ?? null,
                'currency_code'     => $data['currency_code'] ?? 'SAR',
                'is_active'         => $data['is_active'] ?? true,
                'created_by'        => $userId,
            ]);
        });
    }

    /**
     * Update an existing work center.
     */
    public function updateWorkCenter(WorkCenter $workCenter, array $data, int $userId): WorkCenter
    {
        return DB::transaction(function () use ($workCenter, $data): WorkCenter {
            $workCenter->update(array_filter([
                'code'               => $data['code'] ?? $workCenter->code,
                'name'               => $data['name'] ?? $workCenter->name,
                'description'        => $data['description'] ?? $workCenter->description,
                'work_center_type'   => $data['work_center_type'] ?? $workCenter->work_center_type,
                'capacity_per_day'   => $data['capacity_per_day'] ?? $workCenter->capacity_per_day,
                'efficiency_percent' => $data['efficiency_percent'] ?? $workCenter->efficiency_percent,
                'calendar_type'      => $data['calendar_type'] ?? $workCenter->calendar_type,
                'cost_per_hour'      => $data['cost_per_hour'] ?? $workCenter->cost_per_hour,
                'currency_code'      => $data['currency_code'] ?? $workCenter->currency_code,
                'is_active'          => $data['is_active'] ?? $workCenter->is_active,
            ], fn($v) => $v !== null));

            return $workCenter->fresh();
        });
    }

    /**
     * Set or replace a calendar exception for a work center on a specific date.
     */
    public function setException(
        WorkCenter $workCenter,
        string $date,
        float $availableHours,
        string $reason,
        int $userId
    ): WorkCenterException {
        return DB::transaction(function () use ($workCenter, $date, $availableHours, $reason): WorkCenterException {
            return WorkCenterException::updateOrCreate(
                [
                    'work_center_id' => $workCenter->id,
                    'exception_date' => $date,
                ],
                [
                    'available_hours' => $availableHours,
                    'reason'          => $reason,
                ]
            );
        });
    }

    /**
     * Plan capacity for all operations belonging to a work order.
     * Finds the earliest available slot per work center and creates
     * CapacityRequirement + CapacityLoad records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function planCapacity(int $workOrderId, int $userId): array
    {
        return DB::transaction(function () use ($workOrderId): array {
            $workOrder = WorkOrder::with(['operations'])->findOrFail($workOrderId);

            // Cancel any existing planned/scheduled requirements for this work order
            $this->releaseCapacity($workOrderId, 0);

            $requirements = [];
            $cursor       = Carbon::parse($workOrder->planned_start_date);

            foreach ($workOrder->operations as $operation) {
                $workCenterId = $operation->work_center_id ?? null;

                if ($workCenterId === null) {
                    continue;
                }

                $workCenter    = WorkCenter::with(['exceptions'])->findOrFail($workCenterId);
                $requiredHours = $this->operationHours($operation);

                [$scheduledStart, $scheduledEnd] = $this->findEarliestSlot(
                    $workCenter,
                    $cursor,
                    $requiredHours
                );

                $requirement = CapacityRequirement::create([
                    'organization_id' => $workOrder->organization_id,
                    'work_order_id'   => $workOrder->id,
                    'work_center_id'  => $workCenter->id,
                    'operation_id'    => $operation->id,
                    'required_hours'  => $requiredHours,
                    'scheduled_start' => $scheduledStart,
                    'scheduled_end'   => $scheduledEnd,
                    'status'          => CapacityRequirement::STATUS_SCHEDULED,
                ]);

                // Update daily load records for each day the operation spans
                $this->addToCapacityLoad(
                    $workCenter,
                    $scheduledStart,
                    $scheduledEnd,
                    $requiredHours
                );

                $requirements[] = $requirement->toArray();
                // Next operation starts when this one ends
                $cursor = $scheduledEnd;
            }

            return $requirements;
        });
    }

    /**
     * Return capacity load per work center per day for the given date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCapacityLoad(
        int $orgId,
        string $from,
        string $to,
        ?int $workCenterId = null
    ): array {
        $query = CapacityLoad::withoutGlobalScope('organization')
            ->with('workCenter')
            ->where('organization_id', $orgId)
            ->whereBetween('load_date', [$from, $to])
            ->orderBy('work_center_id')
            ->orderBy('load_date');

        if ($workCenterId !== null) {
            $query->where('work_center_id', $workCenterId);
        }

        return $query->get()->map(function (CapacityLoad $load): array {
            $utilization = $load->getUtilizationPercent();

            return [
                'work_center_id'    => $load->work_center_id,
                'work_center_code'  => $load->workCenter?->code,
                'work_center_name'  => $load->workCenter?->name,
                'date'              => $load->load_date->toDateString(),
                'planned_hours'     => (float) $load->planned_hours,
                'actual_hours'      => (float) $load->actual_hours,
                'available_hours'   => (float) $load->available_hours,
                'utilization_percent' => $utilization,
                'is_overloaded'     => $load->isOverloaded(),
            ];
        })->toArray();
    }

    /**
     * Find work centers that are over 90% utilised for any day in the range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detectBottlenecks(int $orgId, string $from, string $to): array
    {
        // DB-level filter: only rows where planned_hours > 90% of available_hours.
        $rows = CapacityLoad::withoutGlobalScope('organization')
            ->join('work_centers', 'work_centers.id', '=', 'capacity_loads.work_center_id')
            ->where('capacity_loads.organization_id', $orgId)
            ->whereBetween('capacity_loads.load_date', [$from, $to])
            ->whereRaw('capacity_loads.planned_hours > capacity_loads.available_hours * 0.9')
            ->selectRaw('
                capacity_loads.work_center_id,
                work_centers.code  AS work_center_code,
                work_centers.name  AS work_center_name,
                capacity_loads.load_date,
                capacity_loads.planned_hours,
                capacity_loads.available_hours
            ')
            ->orderByRaw('capacity_loads.planned_hours / NULLIF(capacity_loads.available_hours, 0) DESC')
            ->get();

        $bottlenecks = [];

        foreach ($rows as $row) {
            $avail       = (float) $row->available_hours;
            $planned     = (float) $row->planned_hours;
            $utilization = $avail > 0.0 ? round(($planned / $avail) * 100, 2) : 999.99;

            $bottlenecks[] = [
                'work_center_id'      => $row->work_center_id,
                'work_center_code'    => $row->work_center_code,
                'work_center_name'    => $row->work_center_name,
                'date'                => $row->load_date,
                'planned_hours'       => $planned,
                'available_hours'     => $avail,
                'utilization_percent' => $utilization,
                'is_overloaded'       => $planned > $avail,
            ];
        }

        // Already sorted by DB; usort removed

        return $bottlenecks;
    }

    /**
     * Shift all capacity requirements for a work order to a new start date.
     * Recalculates scheduling using the same logic as planCapacity.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rescheduleOrder(int $workOrderId, string $newStartDate, int $userId): array
    {
        return DB::transaction(function () use ($workOrderId, $newStartDate, $userId): array {
            $workOrder = WorkOrder::findOrFail($workOrderId);
            $workOrder->update(['planned_start_date' => $newStartDate]);

            return $this->planCapacity($workOrderId, $userId);
        });
    }

    /**
     * Cancel all capacity requirements for a work order and reduce the load records.
     */
    public function releaseCapacity(int $workOrderId, int $userId): void
    {
        DB::transaction(function () use ($workOrderId): void {
            $requirements = CapacityRequirement::where('work_order_id', $workOrderId)
                ->active()
                ->get();

            foreach ($requirements as $req) {
                if ($req->scheduled_start !== null && $req->scheduled_end !== null) {
                    $this->subtractFromCapacityLoad(
                        $req->work_center_id,
                        $req->scheduled_start,
                        $req->scheduled_end,
                        (float) $req->required_hours
                    );
                }

                $req->update(['status' => CapacityRequirement::STATUS_CANCELLED]);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Capacity Leveling
    // -------------------------------------------------------------------------

    /**
     * Level capacity for all scheduled work orders in the given date range.
     *
     * Strategies:
     *   'delay' — push overloaded work orders forward to the next available period.
     *   'split' — split large work orders across periods to fit within capacity.
     *
     * @return array<int, array<string, mixed>>
     */
    public function levelCapacity(
        int $organizationId,
        string $fromDate,
        string $toDate,
        string $strategy = 'delay'
    ): array {
        return DB::transaction(function () use ($organizationId, $fromDate, $toDate, $strategy): array {
            // Only fetch overloaded rows directly from DB — no full collection load.
            $overloadedLoads = CapacityLoad::withoutGlobalScope('organization')
                ->with('workCenter')
                ->where('organization_id', $organizationId)
                ->whereBetween('load_date', [$fromDate, $toDate])
                ->whereRaw('planned_hours > available_hours')
                ->get();

            $rescheduled = [];

            foreach ($overloadedLoads as $load) {
                $workCenterId = $load->work_center_id;

                // Find requirements on this date for this work center
                $requirements = CapacityRequirement::withoutGlobalScope('organization')
                    ->with('workOrder')
                    ->where('organization_id', $organizationId)
                    ->where('work_center_id', $workCenterId)
                    ->whereDate('scheduled_start', $load->load_date)
                    ->active()
                    ->orderByDesc('required_hours')
                    ->get();

                foreach ($requirements as $req) {
                    if ($req->workOrder === null) {
                        continue;
                    }

                    $workOrder    = $req->workOrder;
                    $oldStartDate = $workOrder->planned_start_date?->toDateString();

                    if ($strategy === 'delay') {
                        // Shift the whole work order to the next available date
                        $nextDate = $this->findNextAvailableDate(
                            $load->workCenter,
                            Carbon::parse($load->load_date)->addDay(),
                            (float) $req->required_hours
                        );

                        if ($nextDate !== null) {
                            $this->rescheduleOrder($workOrder->id, $nextDate->toDateString(), 0);
                            $rescheduled[] = [
                                'work_order_id'     => $workOrder->id,
                                'work_order_number' => $workOrder->work_order_number,
                                'action'            => 'delayed',
                                'old_start_date'    => $oldStartDate,
                                'new_start_date'    => $nextDate->toDateString(),
                                'work_center_id'    => $workCenterId,
                            ];
                        }
                    } elseif ($strategy === 'split') {
                        // Split: reduce hours to available capacity, push remainder to next day
                        $available = (float) $load->available_hours;
                        $needed    = (float) $req->required_hours;

                        if ($needed > $available && $available > 0.0) {
                            $nextDate = Carbon::parse($load->load_date)->addDay();
                            $this->rescheduleOrder($workOrder->id, $nextDate->toDateString(), 0);
                            $rescheduled[] = [
                                'work_order_id'     => $workOrder->id,
                                'work_order_number' => $workOrder->work_order_number,
                                'action'            => 'split',
                                'old_start_date'    => $oldStartDate,
                                'new_start_date'    => $nextDate->toDateString(),
                                'work_center_id'    => $workCenterId,
                                'hours_deferred'    => round($needed - $available, 2),
                            ];
                        }
                    }
                }
            }

            return $rescheduled;
        });
    }

    /**
     * Find an alternative work center of the same type with capacity available
     * within the work order's planned time window.
     */
    public function findAlternativeWorkCenter(WorkOrder $workOrder, int $operationId): ?WorkCenter
    {
        $operation = CapacityRequirement::where('work_order_id', $workOrder->id)
            ->where('operation_id', $operationId)
            ->first();

        if ($operation === null) {
            return null;
        }

        $currentWorkCenter = WorkCenter::find($operation->work_center_id);

        if ($currentWorkCenter === null) {
            return null;
        }

        $fromDate = $workOrder->planned_start_date ?? now();
        $toDate   = $workOrder->planned_end_date ?? now()->addDays(30);

        $alternatives = WorkCenter::withoutGlobalScope('organization')
            ->where('organization_id', $workOrder->organization_id)
            ->where('work_center_type', $currentWorkCenter->work_center_type)
            ->where('id', '!=', $currentWorkCenter->id)
            ->where('is_active', true)
            ->get();

        foreach ($alternatives as $candidate) {
            $cursor = Carbon::parse($fromDate);
            $limit  = Carbon::parse($toDate);

            while ($cursor->lte($limit)) {
                $available = $this->availableCapacityOnDate($candidate, $cursor->toDateTime());

                if ($available >= (float) $operation->required_hours) {
                    return $candidate;
                }

                $cursor->addDay();
            }
        }

        return null;
    }

    /**
     * Return suggested capacity leveling actions without applying them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestLevelingActions(int $organizationId, string $fromDate, string $toDate): array
    {
        $suggestions = [];

        $overloadedLoads = CapacityLoad::withoutGlobalScope('organization')
            ->with('workCenter')
            ->where('organization_id', $organizationId)
            ->whereBetween('load_date', [$fromDate, $toDate])
            ->whereRaw('planned_hours > available_hours')
            ->get();

        foreach ($overloadedLoads as $load) {
            $requirements = CapacityRequirement::withoutGlobalScope('organization')
                ->with('workOrder')
                ->where('organization_id', $organizationId)
                ->where('work_center_id', $load->work_center_id)
                ->whereDate('scheduled_start', $load->load_date)
                ->active()
                ->get();

            foreach ($requirements as $req) {
                if ($req->workOrder === null) {
                    continue;
                }

                $nextDate = $this->findNextAvailableDate(
                    $load->workCenter,
                    Carbon::parse($load->load_date)->addDay(),
                    (float) $req->required_hours
                );

                $suggestions[] = [
                    'work_order_id'     => $req->work_order_id,
                    'work_order_number' => $req->workOrder->work_order_number,
                    'current_date'      => $load->load_date->toDateString(),
                    'suggested_date'    => $nextDate?->toDateString(),
                    'work_center_id'    => $load->work_center_id,
                    'work_center_name'  => $load->workCenter?->name,
                    'action_type'       => 'delay',
                    'load_reduction'    => round((float) $req->required_hours, 2),
                    'overload_hours'    => round((float) $load->planned_hours - (float) $load->available_hours, 2),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Find the next date after $from where $workCenter has at least $requiredHours available.
     */
    private function findNextAvailableDate(WorkCenter $workCenter, Carbon $from, float $requiredHours): ?Carbon
    {
        $cursor = $from->copy();
        $limit  = $from->copy()->addDays(60);

        while ($cursor->lte($limit)) {
            $available = $this->availableCapacityOnDate($workCenter, $cursor->toDateTime());

            if ($available >= $requiredHours) {
                return $cursor->copy();
            }

            $cursor->addDay();
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return the estimated hours for a work-order operation.
     */
    private function operationHours(WorkOrderOperation $operation): float
    {
        $minutes = (int) ($operation->estimated_minutes ?? 0);

        return $minutes > 0 ? round($minutes / 60, 2) : 1.0;
    }

    /**
     * Walk forward from $startFrom until $requiredHours can be accommodated.
     * Returns [Carbon $start, Carbon $end].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function findEarliestSlot(
        WorkCenter $workCenter,
        Carbon $startFrom,
        float $requiredHours
    ): array {
        $cursor = $startFrom->copy();
        $remaining = $requiredHours;
        $scheduledStart = null;

        // Safety: do not search further than 365 days
        $limit = $startFrom->copy()->addDays(365);

        while ($remaining > 0.0 && $cursor->lt($limit)) {
            $available = $this->availableCapacityOnDate($workCenter, $cursor->toDateTime());

            if ($available > 0.0) {
                if ($scheduledStart === null) {
                    $scheduledStart = $cursor->copy()->setTime(8, 0);
                }

                $consumed  = min($available, $remaining);
                $remaining = (float) bcsub((string) $remaining, (string) $consumed, 4);
            }

            $cursor->addDay();
        }

        $scheduledStart ??= $startFrom->copy()->setTime(8, 0);
        $scheduledEnd   = $cursor->copy()->setTime(17, 0);

        return [$scheduledStart, $scheduledEnd];
    }

    /**
     * Get remaining available hours on a given date by checking what's
     * already committed in capacity_loads.
     */
    private function availableCapacityOnDate(WorkCenter $workCenter, \DateTime $date): float
    {
        $dateStr   = $date->format('Y-m-d');
        $maxHours  = $workCenter->getAvailableHoursForDate($date);

        if (bccomp((string) $maxHours, '0', 4) <= 0) {
            return 0.0;
        }

        $load = CapacityLoad::where('work_center_id', $workCenter->id)
            ->where('load_date', $dateStr)
            ->first();

        $alreadyPlanned = $load !== null ? (string) $load->planned_hours : '0';

        $available = bcsub((string) $maxHours, $alreadyPlanned, 4);

        if (bccomp($available, '0', 4) < 0) {
            $available = '0.0000';
        }

        return (float) $available;
    }

    /**
     * Distribute requiredHours across capacity_loads from scheduledStart to scheduledEnd.
     */
    private function addToCapacityLoad(
        WorkCenter $workCenter,
        Carbon $scheduledStart,
        Carbon $scheduledEnd,
        float $requiredHours
    ): void {
        $orgId     = $workCenter->organization_id;
        $cursor    = $scheduledStart->copy()->startOfDay();
        $remaining = $requiredHours;

        while ($cursor->lte($scheduledEnd) && $remaining > 0.0) {
            $available = $workCenter->getAvailableHoursForDate($cursor->toDateTime());
            $dateStr   = $cursor->toDateString();

            if ($available > 0.0) {
                $toAdd    = min($available, $remaining);
                $remaining = (float) bcsub((string) $remaining, (string) $toAdd, 4);

                CapacityLoad::updateOrCreate(
                    [
                        'work_center_id' => $workCenter->id,
                        'load_date'      => $dateStr,
                    ],
                    [
                        'organization_id' => $orgId,
                        'available_hours' => $available,
                    ]
                );

                CapacityLoad::where('work_center_id', $workCenter->id)
                    ->where('load_date', $dateStr)
                    ->increment('planned_hours', $toAdd);
            }

            $cursor->addDay();
        }
    }

    /**
     * Subtract hours from capacity_loads when releasing a requirement.
     */
    private function subtractFromCapacityLoad(
        int $workCenterId,
        Carbon $scheduledStart,
        Carbon $scheduledEnd,
        float $requiredHours
    ): void {
        $cursor    = $scheduledStart->copy()->startOfDay();
        $remaining = $requiredHours;

        while ($cursor->lte($scheduledEnd) && $remaining > 0.0) {
            $dateStr = $cursor->toDateString();
            $load    = CapacityLoad::where('work_center_id', $workCenterId)
                ->where('load_date', $dateStr)
                ->first();

            if ($load !== null && (float) $load->planned_hours > 0.0) {
                $toSubtract = min((float) $load->planned_hours, $remaining);
                $remaining  = (float) bcsub((string) $remaining, (string) $toSubtract, 4);
                $newPlanned = max(0.0, (float) bcsub((string) $load->planned_hours, (string) $toSubtract, 4));

                $load->update(['planned_hours' => $newPlanned]);
            }

            $cursor->addDay();
        }
    }
}
