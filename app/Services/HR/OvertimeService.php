<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\OvertimePolicy;
use App\Models\HR\OvertimeRequest;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    /**
     * Monthly standard work-hours divisor used for hourly rate calculation.
     */
    private const MONTHLY_HOURS = 208.0;

    /**
     * Create an overtime request, validating against policy limits.
     */
    public function requestOvertime(array $data): OvertimeRequest
    {
        $employee = Employee::findOrFail($data['employee_id']);
        $policy   = OvertimePolicy::findOrFail($data['policy_id']);

        $otHours  = (float) $data['ot_hours'];
        $dayType  = $data['day_type'] ?? OvertimeRequest::DAY_TYPE_WEEKDAY;
        $otRate   = $policy->getRateForDayType($dayType);
        $otDate   = $data['ot_date'];

        // Validate daily limit
        if ($otHours > (float) $policy->max_daily_ot_hours) {
            throw new \InvalidArgumentException(
                "OT hours ({$otHours}) exceed daily maximum ({$policy->max_daily_ot_hours}) for this policy."
            );
        }

        // Validate weekly limit
        $weekStart    = (new \DateTime($otDate))->modify('monday this week')->format('Y-m-d');
        $weekEnd      = (new \DateTime($otDate))->modify('sunday this week')->format('Y-m-d');
        $weeklyTotal  = OvertimeRequest::forEmployee($employee->id)
            ->whereBetween('ot_date', [$weekStart, $weekEnd])
            ->whereIn('status', [OvertimeRequest::STATUS_PENDING, OvertimeRequest::STATUS_APPROVED])
            ->sum('ot_hours');

        if (($weeklyTotal + $otHours) > (float) $policy->max_weekly_ot_hours) {
            throw new \InvalidArgumentException(
                "This request would exceed the weekly OT limit ({$policy->max_weekly_ot_hours} hours)."
            );
        }

        return OvertimeRequest::create([
            'employee_id' => $employee->id,
            'policy_id'   => $policy->id,
            'ot_date'     => $otDate,
            'ot_start'    => $data['ot_start'],
            'ot_end'      => $data['ot_end'],
            'ot_hours'    => $otHours,
            'reason'      => $data['reason'] ?? null,
            'day_type'    => $dayType,
            'ot_rate'     => $otRate,
            'ot_amount'   => 0,
            'status'      => $policy->requires_approval
                ? OvertimeRequest::STATUS_PENDING
                : OvertimeRequest::STATUS_APPROVED,
            'created_by'  => auth()->id(),
        ]);
    }

    /**
     * Approve overtime request and calculate ot_amount.
     */
    public function approve(OvertimeRequest $request): OvertimeRequest
    {
        if (!$request->isPending()) {
            throw new \InvalidArgumentException(
                "Only pending requests can be approved. Current status: '{$request->status}'."
            );
        }

        return DB::transaction(function () use ($request): OvertimeRequest {
            $hourlyRate = $this->calculateHourlyRate($request->employee);
            $otAmount   = round((float) $request->ot_hours * $hourlyRate * (float) $request->ot_rate, 4);

            $request->update([
                'status'      => OvertimeRequest::STATUS_APPROVED,
                'ot_amount'   => $otAmount,
                'approved_by' => auth()->id(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Reject overtime request.
     */
    public function reject(OvertimeRequest $request, string $reason): OvertimeRequest
    {
        if (!$request->isPending()) {
            throw new \InvalidArgumentException(
                "Only pending requests can be rejected. Current status: '{$request->status}'."
            );
        }

        $request->update([
            'status'  => OvertimeRequest::STATUS_REJECTED,
            'reason'  => $reason,
        ]);

        return $request->fresh();
    }

    /**
     * Get monthly OT summary for payroll integration.
     *
     * @return array{total_hours: float, total_amount: float, request_count: int, approved_count: int}
     */
    public function getMonthlyOtSummary(int $employeeId, int $year, int $month): array
    {
        $rows = OvertimeRequest::forEmployee($employeeId)
            ->inMonth($year, $month)
            ->selectRaw('status, COUNT(*) as cnt, SUM(ot_hours) as hours, SUM(ot_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $approved = $rows->get(OvertimeRequest::STATUS_APPROVED);
        $paid     = $rows->get(OvertimeRequest::STATUS_PAID);

        return [
            'employee_id'    => $employeeId,
            'year'           => $year,
            'month'          => $month,
            'total_hours'    => (float) ($approved?->hours ?? 0) + (float) ($paid?->hours ?? 0),
            'total_amount'   => (float) ($approved?->amount ?? 0) + (float) ($paid?->amount ?? 0),
            'request_count'  => $rows->sum('cnt'),
            'approved_count' => (int) ($approved?->cnt ?? 0),
            'paid_count'     => (int) ($paid?->cnt ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function calculateHourlyRate(Employee $employee): float
    {
        $basicSalary = (float) ($employee->currentSalary?->basic_salary ?? 0);

        if ($basicSalary <= 0) {
            return 0.0;
        }

        return round($basicSalary / self::MONTHLY_HOURS, 4);
    }
}
