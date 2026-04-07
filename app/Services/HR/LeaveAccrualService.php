<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveBalance;
use App\Models\HR\Leave\LeaveEncashment;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\Leave\LeaveType;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    /**
     * Process accruals for all employees in an organization.
     */
    public function processAccruals(int $organizationId, ?string $accrualDate = null): int
    {
        $accrualDate = $accrualDate ? new \DateTime($accrualDate) : new \DateTime();
        $processed = 0;

        $policies = LeavePolicy::where('organization_id', $organizationId)
            ->active()
            ->with(['leaveTypes' => function ($q) {
                $q->active()->with(['leaveTiers' => function ($q2) {
                    $q2->active()->byPriority();
                }]);
            }])
            ->get();

        $employees = Employee::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->get();

        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                foreach ($policy->leaveTypes as $leaveType) {
                    if (!$leaveType->isApplicableToEmployee($employee)) {
                        continue;
                    }

                    if ($leaveType->accrual_type === LeaveType::ACCRUAL_NONE) {
                        continue;
                    }

                    $tier = $this->getApplicableTier($leaveType, $employee);
                    if (!$tier) {
                        continue;
                    }

                    $accrued = $this->processEmployeeAccrual(
                        $employee,
                        $leaveType,
                        $tier,
                        $accrualDate
                    );

                    if ($accrued) {
                        $processed++;
                    }
                }
            }
        }

        return $processed;
    }

    /**
     * Adjust an employee's leave balance.
     */
    public function adjustBalance(array $data): LeaveAdjustment
    {
        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('year', $data['year'] ?? now()->year)
                ->firstOrFail();

            $balanceBefore = (float) $balance->available_balance;

            $adjustment = LeaveAdjustment::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'leave_balance_id' => $balance->id,
                'adjustment_type' => $data['adjustment_type'],
                'days' => $data['days'],
                'balance_before' => $balanceBefore,
                'balance_after' => 0,
                'reason' => $data['reason'],
                'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => !empty($data['approved_by']) ? now() : null,
                'created_by' => $data['created_by'],
            ]);

            $days = (float) $data['days'];
            if ($data['adjustment_type'] === LeaveAdjustment::TYPE_DEDUCT) {
                $days = -$days;
            }

            $balance->adjustment_days = bcadd((string) $balance->adjustment_days, (string) $days, 2);
            $balance->recalculateAvailableBalance();
            $balance->save();

            $adjustment->update(['balance_after' => $balance->available_balance]);

            return $adjustment->fresh();
        });
    }

    /**
     * Process a leave encashment request.
     */
    public function encashLeave(array $data): LeaveEncashment
    {
        return DB::transaction(function () use ($data) {
            $balance = LeaveBalance::where('employee_id', $data['employee_id'])
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('year', $data['year'] ?? now()->year)
                ->firstOrFail();

            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            if (!$leaveType->is_encashable) {
                throw new \InvalidArgumentException('This leave type is not encashable.');
            }

            $requestedDays = (float) $data['requested_days'];
            if ($requestedDays > $balance->getAvailableBalance()) {
                throw new \InvalidArgumentException('Insufficient leave balance for encashment.');
            }

            $tier = $balance->leaveTier;
            if ($tier && $tier->max_encashable_days && $requestedDays > $tier->max_encashable_days) {
                throw new \InvalidArgumentException("Maximum encashable days is {$tier->max_encashable_days}.");
            }

            $encashmentRate = $tier?->encashment_rate ?? 100;

            $encashment = LeaveEncashment::create([
                'organization_id' => $data['organization_id'],
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'leave_balance_id' => $balance->id,
                'requested_days' => $requestedDays,
                'daily_rate' => $data['daily_rate'],
                'encashment_rate' => $encashmentRate,
                'amount' => bcmul(
                    bcmul((string) $requestedDays, (string) $data['daily_rate'], 4),
                    bcdiv((string) $encashmentRate, '100', 4),
                    4
                ),
                'status' => LeaveEncashment::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'],
            ]);

            return $encashment;
        });
    }

    /**
     * Get the current balance for an employee and leave type.
     */
    public function getBalance(int $employeeId, int $leaveTypeId, ?int $year = null): ?LeaveBalance
    {
        $year = $year ?? now()->year;

        return LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->with(['leaveType', 'leaveTier'])
            ->first();
    }

    /**
     * Get the applicable tier for an employee based on service months and other criteria.
     */
    protected function getApplicableTier(LeaveType $leaveType, Employee $employee): ?LeaveTier
    {
        $serviceMonths = $employee->getTenureInMonths();

        return $leaveType->leaveTiers()
            ->active()
            ->forServiceMonths($serviceMonths)
            ->when($employee->grade ?? null, function ($q, $grade) {
                $q->where(function ($q2) use ($grade) {
                    $q2->whereNull('employee_grade')->orWhere('employee_grade', $grade);
                });
            })
            ->byPriority()
            ->first();
    }

    /**
     * Process accrual for a single employee and leave type.
     */
    protected function processEmployeeAccrual(
        Employee $employee,
        LeaveType $leaveType,
        LeaveTier $tier,
        \DateTimeInterface $accrualDate
    ): bool {
        $year = (int) $accrualDate->format('Y');

        $accrualDays = $tier->monthly_accrual_rate ?? round((float) $tier->entitled_days / 12, 2);

        return DB::transaction(function () use ($employee, $leaveType, $tier, $year, $accrualDate, $accrualDays) {
            // Lock or create the balance row atomically to prevent duplicate accruals
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = LeaveBalance::create([
                    'employee_id'      => $employee->id,
                    'leave_type_id'    => $leaveType->id,
                    'year'             => $year,
                    'organization_id'  => $employee->organization_id,
                    'leave_tier_id'    => $tier->id,
                    'opening_balance'  => 0,
                    'entitled_days'    => $tier->entitled_days,
                    'available_balance' => $tier->entitled_days,
                ]);
            }

            if ($balance->last_accrual_date && $balance->last_accrual_date >= $accrualDate) {
                return false;
            }

            LeaveAccrual::create([
                'leave_balance_id' => $balance->id,
                'employee_id'      => $employee->id,
                'accrual_date'     => $accrualDate->format('Y-m-d'),
                'accrual_type'     => LeaveAccrual::TYPE_MONTHLY,
                'days'             => $accrualDays,
                'description'      => 'Monthly accrual',
            ]);

            $balance->accrued_days = bcadd((string) $balance->accrued_days, (string) $accrualDays, 2);
            $balance->last_accrual_date = $accrualDate;
            $balance->recalculateAvailableBalance();
            $balance->save();

            return true;
        });
    }
}
