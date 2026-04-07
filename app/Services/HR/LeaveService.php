<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\Core\UserEvent;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Services\Core\UserEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveService
{
    public function __construct(
        private UserEventService $userEventService
    ) {}

    /**
     * Create a leave request.
     */
    public function createRequest(Employee $employee, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data) {
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            // Validate applicability
            if (!$leaveType->isApplicableToEmployee($employee)) {
                throw new \InvalidArgumentException('This leave type is not applicable to you.');
            }

            // Calculate total days
            $fromDate = new \DateTime($data['from_date']);
            $toDate = new \DateTime($data['to_date']);
            $totalDays = $this->calculateLeaveDays($employee, $fromDate, $toDate, $data['is_half_day'] ?? false);

            // Check balance
            $balance = $this->getBalance($employee, $leaveType);
            if ($totalDays > $balance) {
                throw new \InvalidArgumentException("Insufficient leave balance. Available: {$balance} days, Requested: {$totalDays} days.");
            }

            // Check for overlapping requests
            $request = new LeaveRequest([
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'],
                'total_days' => $totalDays,
                'is_half_day' => $data['is_half_day'] ?? false,
                'half_day_type' => $data['half_day_type'] ?? null,
                'reason' => $data['reason'] ?? null,
                'contact_during_leave' => $data['contact_during_leave'] ?? null,
                'address_during_leave' => $data['address_during_leave'] ?? null,
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            $overlaps = LeaveRequest::where('employee_id', $employee->id)
                ->where('id', '!=', $request->id)
                ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
                ->where(function ($query) use ($data) {
                    $query->whereBetween('from_date', [$data['from_date'], $data['to_date']])
                        ->orWhereBetween('to_date', [$data['from_date'], $data['to_date']])
                        ->orWhere(function ($q) use ($data) {
                            $q->where('from_date', '<=', $data['from_date'])
                                ->where('to_date', '>=', $data['to_date']);
                        });
                })
                ->lockForUpdate()
                ->exists();

            if ($overlaps) {
                throw new \InvalidArgumentException('You already have a leave request for these dates.');
            }

            // Check attachment requirement
            if ($leaveType->requiresAttachmentForDays($totalDays) && empty($data['attachment_path'])) {
                throw new \InvalidArgumentException('Attachment is required for leave exceeding ' . $leaveType->attachment_required_after_days . ' days.');
            }

            $request->attachment_path = $data['attachment_path'] ?? null;
            $request->save();

            return $request;
        });
    }

    /**
     * Submit a leave request for approval.
     */
    public function submit(LeaveRequest $request): LeaveRequest
    {
        if (!$request->isEditable()) {
            throw new \InvalidArgumentException('Leave request cannot be submitted.');
        }

        $request->update(['status' => LeaveRequest::STATUS_PENDING]);

        $request = $request->fresh();

        try {
            $this->userEventService->track(
                UserEvent::LEAVE_REQUESTED,
                ['leave_request_id' => $request->id, 'employee_id' => $request->employee_id, 'total_days' => $request->total_days],
                auth('api')->id(),
                $request->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::LEAVE_REQUESTED, 'error' => $e->getMessage()]);
        }

        return $request;
    }

    /**
     * Approve a leave request.
     */
    public function approve(LeaveRequest $request, int $userId): LeaveRequest
    {
        if (!$request->isPending()) {
            throw new \InvalidArgumentException('Only pending requests can be approved.');
        }

        $request = DB::transaction(function () use ($request, $userId) {
            // Lock the balance row to prevent concurrent approvals from double-deducting
            $balance = LeaveBalance::where('employee_id', $request->employee_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('year', $request->from_date->year)
                ->lockForUpdate()
                ->first();

            if ($balance && !$balance->hasBalance((float) $request->total_days)) {
                $available = $balance->getAvailableBalance();
                throw new \InvalidArgumentException(
                    "Insufficient leave balance. Available: {$available} days, Requested: {$request->total_days} days."
                );
            }

            $request->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Deduct from balance
            if ($balance) {
                $balance->deductLeave((float) $request->total_days);
            }

            // Mark attendance as on leave
            $this->markAttendanceAsLeave($request);

            return $request->fresh();
        });

        try {
            $this->userEventService->track(
                UserEvent::LEAVE_APPROVED,
                ['leave_request_id' => $request->id, 'employee_id' => $request->employee_id, 'total_days' => $request->total_days],
                auth('api')->id(),
                $request->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::LEAVE_APPROVED, 'error' => $e->getMessage()]);
        }

        return $request;
    }

    /**
     * Reject a leave request.
     */
    public function reject(LeaveRequest $request, string $reason, int $userId): LeaveRequest
    {
        if (!$request->isPending()) {
            throw new \InvalidArgumentException('Only pending requests can be rejected.');
        }

        $request = DB::transaction(function () use ($request, $reason, $userId) {
            $request->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $request->fresh();
        });

        // Notify AFTER the transaction commits — notifications must never hold open
        // a DB transaction (they can block on mail/push delivery).
        $employee = $request->employee;
        if ($employee?->user) {
            $employee->user->notify(new \App\Notifications\HR\LeaveRequestRejectedNotification($request, $reason));
        }

        try {
            $this->userEventService->track(
                UserEvent::LEAVE_REJECTED,
                ['leave_request_id' => $request->id, 'employee_id' => $request->employee_id, 'reason' => $reason],
                auth('api')->id(),
                $request->organization_id,
            );
        } catch (\Throwable $e) {
            Log::warning('Event tracking failed', ['event' => UserEvent::LEAVE_REJECTED, 'error' => $e->getMessage()]);
        }

        return $request;
    }

    /**
     * Cancel a leave request.
     */
    public function cancel(LeaveRequest $request, string $reason): LeaveRequest
    {
        if (!$request->canBeCancelled()) {
            throw new \InvalidArgumentException('Leave request cannot be cancelled.');
        }

        return DB::transaction(function () use ($request, $reason) {
            $wasApproved = $request->isApproved();

            $request->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Restore balance if was approved
            if ($wasApproved) {
                $balance = LeaveBalance::where('employee_id', $request->employee_id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->where('year', $request->from_date->year)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->creditLeave($request->total_days);
                }

                // Restore attendance records — iterate so HasAuditTrail fires per record.
                Attendance::where('employee_id', $request->employee_id)
                    ->whereBetween('attendance_date', [$request->from_date, $request->to_date])
                    ->where('status', Attendance::STATUS_ON_LEAVE)
                    ->each(fn (Attendance $a) => $a->update(['status' => Attendance::STATUS_ABSENT]));
            }

            return $request->fresh();
        });
    }

    /**
     * Get leave balance for employee.
     */
    public function getBalance(Employee $employee, LeaveType $leaveType, ?int $year = null): float
    {
        $year = $year ?? now()->year;

        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        return $balance ? $balance->getAvailableBalance() : 0;
    }

    /**
     * Get all leave balances for employee.
     */
    public function getAllBalances(Employee $employee, ?int $year = null): \Illuminate\Support\Collection
    {
        $year = $year ?? now()->year;

        return LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();
    }

    /**
     * Initialize leave balances for a new year.
     */
    public function initializeYearBalances(int $year, int $organizationId): int
    {
        // Load leave types once — small reference dataset
        $leaveTypes = LeaveType::active()->get();
        $count = 0;

        Employee::active()
            ->where('organization_id', $organizationId)
            ->chunkById(100, function ($employees) use ($leaveTypes, $year, &$count) {
        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                if (!$leaveType->isApplicableToEmployee($employee)) {
                    continue;
                }

                // Check if already exists
                $exists = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Get previous year balance for carry forward
                $openingBalance = 0;
                if ($leaveType->carry_forward) {
                    $prevBalance = LeaveBalance::where('employee_id', $employee->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $year - 1)
                        ->first();

                    if ($prevBalance) {
                        $openingBalance = min(
                            $prevBalance->closing_balance,
                            $leaveType->max_carry_forward_days
                        );
                    }
                }

                // Calculate prorated quota for employees who joined mid-year.
                // Only prorate when the employee joined during the target year;
                // employees who joined in a prior year receive the full quota.
                $accrued = $leaveType->annual_quota;
                if ($leaveType->prorate_on_joining && $employee->joining_date) {
                    if ($employee->joining_date->year !== $year) {
                        // Joined before this year — full quota, no proration
                        $accrued = $leaveType->annual_quota;
                    } else {
                        // Joined mid-year — prorate from joining month to end of year
                        $monthsRemaining = 13 - $employee->joining_date->month;
                        $accrued = (float) bcdiv(
                            bcmul((string) $leaveType->annual_quota, (string) $monthsRemaining, 4),
                            '12',
                            4
                        );
                    }
                }

                LeaveBalance::create([
                    'organization_id' => $employee->organization_id,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => $year,
                    'opening_balance' => $openingBalance,
                    'accrued' => $accrued,
                    'closing_balance' => $openingBalance + $accrued,
                ]);

                $count++;
            }
        }
        }); // end chunkById

        return $count;
    }

    /**
     * Calculate leave days excluding non-working days and holidays.
     */
    public function calculateLeaveDays(
        Employee $employee,
        \DateTimeInterface $fromDate,
        \DateTimeInterface $toDate,
        bool $isHalfDay = false
    ): float {
        if ($isHalfDay) {
            return 0.5;
        }

        $holidays = Holiday::where('organization_id', $employee->organization_id)
            ->where(function ($q) use ($employee) {
                // Holidays that apply org-wide (no branch) OR specifically to this branch
                $q->whereNull('branch_id')
                  ->orWhere('branch_id', $employee->branch_id);
            })
            ->whereBetween('holiday_date', [$fromDate, $toDate])
            ->pluck('holiday_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        // Determine working days from the employee's assigned work schedule.
        // Fall back to the standard Mon–Fri week when no schedule is configured.
        $workSchedule = $employee->shift ?? $employee->workSchedule ?? null;
        $workingDays = ($workSchedule !== null && !empty($workSchedule->working_days))
            ? array_map('strtolower', (array) $workSchedule->working_days)
            : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        $period = new \DatePeriod(
            new \DateTime($fromDate->format('Y-m-d')),
            new \DateInterval('P1D'),
            (new \DateTime($toDate->format('Y-m-d')))->modify('+1 day')
        );

        $days = 0;
        foreach ($period as $date) {
            // Skip days not in the employee's working schedule
            if (!in_array(strtolower($date->format('l')), $workingDays)) {
                continue;
            }

            // Skip holidays
            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }

            $days++;
        }

        return $days;
    }

    /**
     * Mark attendance as on leave.
     *
     * Respects the employee's work schedule to determine non-working days,
     * mirroring the logic in calculateLeaveDays(). Falls back to Mon–Fri only
     * when no schedule is configured.
     */
    protected function markAttendanceAsLeave(LeaveRequest $request): void
    {
        $employee = $request->employee;

        // Mirror calculateLeaveDays(): use the employee's shift/work schedule
        // to determine which days are working days.
        $workSchedule = $employee ? ($employee->shift ?? $employee->workSchedule ?? null) : null;
        $workingDays = ($workSchedule !== null && !empty($workSchedule->working_days))
            ? array_map('strtolower', (array) $workSchedule->working_days)
            : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        $period = new \DatePeriod(
            new \DateTime($request->from_date->format('Y-m-d')),
            new \DateInterval('P1D'),
            (new \DateTime($request->to_date->format('Y-m-d')))->modify('+1 day')
        );

        foreach ($period as $date) {
            // Skip non-working days based on the employee's schedule
            if (!in_array(strtolower($date->format('l')), $workingDays)) {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'attendance_date' => $date->format('Y-m-d'),
                ],
                [
                    'organization_id' => $request->organization_id,
                    'status' => Attendance::STATUS_ON_LEAVE,
                    'notes' => "Leave: {$request->leaveType->name}",
                ]
            );
        }
    }

    /**
     * Get leave summary for organization.
     */
    public function getOrganizationSummary(): array
    {
        $orgId = auth()->user()->organization_id;

        $pending = LeaveRequest::where('organization_id', $orgId)->pending()->count();
        $approvedThisMonth = LeaveRequest::where('organization_id', $orgId)->approved()
            ->whereBetween('from_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $onLeaveToday = LeaveRequest::where('organization_id', $orgId)->approved()
            ->where('from_date', '<=', now())
            ->where('to_date', '>=', now())
            ->count();

        return [
            'pending_requests' => $pending,
            'approved_this_month' => $approvedThisMonth,
            'on_leave_today' => $onLeaveToday,
        ];
    }
}
