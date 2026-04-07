<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\ShiftPattern;
use App\Models\HR\ShiftRoster;
use App\Models\HR\ShiftRosterLine;
use App\Models\HR\ShiftSwapRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftPlanningService
{
    /**
     * Create a new shift roster.
     */
    public function createRoster(array $data): ShiftRoster
    {
        $start = Carbon::parse($data['roster_period_start']);
        $end = Carbon::parse($data['roster_period_end']);

        if ($end->lte($start)) {
            throw new \InvalidArgumentException('Roster end date must be after start date.');
        }

        return ShiftRoster::create([
            'organization_id' => auth()->user()->organization_id,
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'name' => $data['name'],
            'roster_period_start' => $data['roster_period_start'],
            'roster_period_end' => $data['roster_period_end'],
            'status' => ShiftRoster::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Publish a roster, making it visible to employees.
     */
    public function publishRoster(ShiftRoster $roster): ShiftRoster
    {
        if (!$roster->canBePublished()) {
            throw new \InvalidArgumentException('Only draft rosters can be published.');
        }

        $roster->update([
            'status' => ShiftRoster::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => auth()->id(),
        ]);

        return $roster->fresh();
    }

    /**
     * Assign a shift to an employee on a roster.
     */
    public function assignShift(
        ShiftRoster $roster,
        Employee $employee,
        string $shiftDate,
        ?ShiftPattern $shiftPattern,
        array $options = []
    ): ShiftRosterLine {
        if (!$roster->isDraft()) {
            throw new \InvalidArgumentException('Shifts can only be assigned to draft rosters.');
        }

        $date = Carbon::parse($shiftDate);
        if ($date->lt($roster->roster_period_start) || $date->gt($roster->roster_period_end)) {
            throw new \InvalidArgumentException('Shift date must be within the roster period.');
        }

        return ShiftRosterLine::updateOrCreate(
            [
                'roster_id' => $roster->id,
                'employee_id' => $employee->id,
                'shift_date' => $shiftDate,
            ],
            [
                'shift_pattern_id' => $shiftPattern?->id,
                'is_day_off' => $options['is_day_off'] ?? false,
                'override_start_time' => $options['override_start_time'] ?? null,
                'override_end_time' => $options['override_end_time'] ?? null,
                'notes' => $options['notes'] ?? null,
            ]
        );
    }

    /**
     * Bulk assign a shift pattern for an employee across a date range.
     */
    public function bulkAssignShift(
        ShiftRoster $roster,
        Employee $employee,
        ShiftPattern $pattern,
        string $fromDate,
        string $toDate
    ): int {
        if (!$roster->isDraft()) {
            throw new \InvalidArgumentException('Shifts can only be assigned to draft rosters.');
        }

        $current = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        $count = 0;

        while ($current->lte($end)) {
            $dayName = strtolower($current->format('l'));
            $daysOfWeek = $pattern->days_of_week ?? [];
            $isWorkingDay = in_array($dayName, $daysOfWeek, true);

            ShiftRosterLine::updateOrCreate(
                [
                    'roster_id' => $roster->id,
                    'employee_id' => $employee->id,
                    'shift_date' => $current->toDateString(),
                ],
                [
                    'shift_pattern_id' => $isWorkingDay ? $pattern->id : null,
                    'is_day_off' => !$isWorkingDay,
                ]
            );

            $count++;
            $current->addDay();
        }

        return $count;
    }

    /**
     * Request a shift swap between two employees.
     */
    public function requestSwap(
        Employee $requester,
        Employee $requestedEmployee,
        string $requesterShiftDate,
        string $requestedShiftDate,
        ?string $reason = null
    ): ShiftSwapRequest {
        if ($requester->id === $requestedEmployee->id) {
            throw new \InvalidArgumentException('Cannot request swap with yourself.');
        }

        if ($requester->organization_id !== $requestedEmployee->organization_id) {
            throw new \InvalidArgumentException('Employees must belong to the same organization.');
        }

        $requesterLine = ShiftRosterLine::where('employee_id', $requester->id)
            ->where('shift_date', $requesterShiftDate)
            ->whereHas('roster', fn($q) => $q->where('status', ShiftRoster::STATUS_PUBLISHED))
            ->first();

        $requestedLine = ShiftRosterLine::where('employee_id', $requestedEmployee->id)
            ->where('shift_date', $requestedShiftDate)
            ->whereHas('roster', fn($q) => $q->where('status', ShiftRoster::STATUS_PUBLISHED))
            ->first();

        return ShiftSwapRequest::create([
            'organization_id' => $requester->organization_id,
            'requester_id' => $requester->id,
            'requested_employee_id' => $requestedEmployee->id,
            'requester_roster_line_id' => $requesterLine?->id,
            'requested_roster_line_id' => $requestedLine?->id,
            'requester_shift_date' => $requesterShiftDate,
            'requested_shift_date' => $requestedShiftDate,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Accept a swap request (by the requested employee).
     */
    public function acceptSwap(ShiftSwapRequest $swapRequest): ShiftSwapRequest
    {
        if (!$swapRequest->isPending()) {
            throw new \InvalidArgumentException('Only pending swap requests can be accepted.');
        }

        $swapRequest->update(['status' => ShiftSwapRequest::STATUS_ACCEPTED]);

        return $swapRequest->fresh();
    }

    /**
     * Approve a swap request (by a manager).
     */
    public function approveSwap(ShiftSwapRequest $swapRequest): ShiftSwapRequest
    {
        if (!$swapRequest->canBeApproved()) {
            throw new \InvalidArgumentException('Swap request must be accepted before it can be approved.');
        }

        return DB::transaction(function () use ($swapRequest) {
            $swapRequest->update([
                'status' => ShiftSwapRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Execute the swap on the roster lines
            if ($swapRequest->requesterRosterLine && $swapRequest->requestedRosterLine) {
                $requesterPatternId = $swapRequest->requesterRosterLine->shift_pattern_id;
                $requestedPatternId = $swapRequest->requestedRosterLine->shift_pattern_id;

                $swapRequest->requesterRosterLine->update(['shift_pattern_id' => $requestedPatternId]);
                $swapRequest->requestedRosterLine->update(['shift_pattern_id' => $requesterPatternId]);
            }

            return $swapRequest->fresh();
        });
    }

    /**
     * Reject a swap request.
     */
    public function rejectSwap(ShiftSwapRequest $swapRequest, string $reason): ShiftSwapRequest
    {
        if (!$swapRequest->isPending()) {
            throw new \InvalidArgumentException('Only pending swap requests can be rejected.');
        }

        $swapRequest->update([
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $swapRequest->fresh();
    }
}
