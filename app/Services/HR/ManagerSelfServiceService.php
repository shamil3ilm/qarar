<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\ManagerDelegation;
use App\Models\HR\ManagerTeamView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManagerSelfServiceService
{
    /**
     * Get all employees under a manager.
     */
    public function getTeam(int $managerId, bool $includeIndirect = false): Collection
    {
        $query = ManagerTeamView::query()
            ->with(['employee.department', 'employee.designation'])
            ->forManager($managerId);

        if (! $includeIndirect) {
            $query->directReports();
        }

        return $query->get()->pluck('employee')->filter()->values();
    }

    /**
     * Get pending approvals for a manager's team.
     */
    public function getPendingApprovals(int $managerId): array
    {
        $teamEmployeeIds = ManagerTeamView::query()
            ->forManager($managerId)
            ->pluck('employee_id')
            ->toArray();

        if (empty($teamEmployeeIds)) {
            return ['leave_requests' => [], 'counts' => ['leave_requests' => 0]];
        }

        $pendingLeaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamEmployeeIds)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return [
            'leave_requests' => $pendingLeaveRequests,
            'counts'         => [
                'leave_requests' => $pendingLeaveRequests->count(),
            ],
        ];
    }

    /**
     * Get attendance for manager's team on a given date.
     */
    public function getTeamAttendance(int $managerId, string $date): Collection
    {
        $teamEmployeeIds = ManagerTeamView::query()
            ->forManager($managerId)
            ->pluck('employee_id')
            ->toArray();

        if (empty($teamEmployeeIds)) {
            return new Collection();
        }

        return Attendance::query()
            ->with(['employee'])
            ->whereIn('employee_id', $teamEmployeeIds)
            ->whereDate('date', $date)
            ->get();
    }

    /**
     * Get leave calendar for manager's team for a given month (YYYY-MM).
     */
    public function getTeamLeaveCalendar(int $managerId, string $month): array
    {
        $teamEmployeeIds = ManagerTeamView::query()
            ->forManager($managerId)
            ->pluck('employee_id')
            ->toArray();

        if (empty($teamEmployeeIds)) {
            return [];
        }

        [$year, $monthNum] = explode('-', $month);

        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->whereIn('employee_id', $teamEmployeeIds)
            ->where('status', 'approved')
            ->whereYear('start_date', (int) $year)
            ->whereMonth('start_date', (int) $monthNum)
            ->orderBy('start_date')
            ->get();

        return $leaveRequests->groupBy(fn($lr) => $lr->start_date->toDateString())->toArray();
    }

    /**
     * Create a delegation record.
     */
    public function createDelegation(array $data): ManagerDelegation
    {
        if ((int) $data['manager_id'] === (int) $data['delegate_id']) {
            throw new InvalidArgumentException('Manager and delegate cannot be the same user.');
        }

        return ManagerDelegation::create([
            'manager_id'      => $data['manager_id'],
            'delegate_id'     => $data['delegate_id'],
            'delegation_type' => $data['delegation_type'] ?? ManagerDelegation::TYPE_FULL,
            'valid_from'      => $data['valid_from'],
            'valid_to'        => $data['valid_to'] ?? null,
            'is_active'       => true,
            'reason'          => $data['reason'] ?? null,
        ]);
    }

    /**
     * Revoke a delegation by marking it inactive.
     */
    public function revokeDelegation(ManagerDelegation $delegation): ManagerDelegation
    {
        $delegation->update(['is_active' => false]);

        return $delegation->fresh();
    }

    /**
     * Rebuild the manager_team_views table for a specific manager.
     * Reads from employees.reports_to (manager_id) column chain.
     */
    public function rebuildTeamView(int $managerId): void
    {
        DB::transaction(function () use ($managerId): void {
            ManagerTeamView::where('manager_id', $managerId)->delete();

            // Direct reports
            $directReports = Employee::query()
                ->whereHas('user', function ($q) use ($managerId) {
                    $q->where('id', $managerId);
                })
                ->orWhere(function ($q) use ($managerId) {
                    // Employees whose manager user_id matches
                    $q->whereHas('department', function ($dq) use ($managerId) {
                        // fallback: no-op, handled below
                    });
                })
                ->get();

            // A simpler approach: find employees whose direct manager FK points here
            $directEmployees = Employee::query()
                ->whereNotNull('reporting_manager_id')
                ->where('reporting_manager_id', $managerId)
                ->get();

            foreach ($directEmployees as $employee) {
                ManagerTeamView::updateOrCreate(
                    ['manager_id' => $managerId, 'employee_id' => $employee->id],
                    [
                        'organization_id'   => $employee->organization_id,
                        'relationship_type' => ManagerTeamView::RELATIONSHIP_DIRECT,
                    ]
                );
            }
        });
    }
}
