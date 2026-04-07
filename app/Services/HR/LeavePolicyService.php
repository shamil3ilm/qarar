<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\Leave\LeaveTierApprover;
use App\Models\HR\Leave\LeaveType;
use Illuminate\Support\Facades\DB;

class LeavePolicyService
{
    /**
     * Create a new leave policy.
     */
    public function create(array $data): LeavePolicy
    {
        return DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                LeavePolicy::where('organization_id', $data['organization_id'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return LeavePolicy::create($data);
        });
    }

    /**
     * Update an existing leave policy.
     */
    public function update(LeavePolicy $policy, array $data): LeavePolicy
    {
        return DB::transaction(function () use ($policy, $data) {
            if (!empty($data['is_default']) && !$policy->is_default) {
                LeavePolicy::where('organization_id', $policy->organization_id)
                    ->where('id', '!=', $policy->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $policy->update($data);

            return $policy->fresh();
        });
    }

    /**
     * Assign a tier to a leave type within a policy.
     */
    public function assignTier(LeaveType $leaveType, array $tierData): LeaveTier
    {
        return DB::transaction(function () use ($leaveType, $tierData) {
            $tier = LeaveTier::create(array_merge($tierData, [
                'leave_type_id' => $leaveType->id,
            ]));

            if (!empty($tierData['approvers'])) {
                foreach ($tierData['approvers'] as $approverData) {
                    LeaveTierApprover::create(array_merge($approverData, [
                        'leave_tier_id' => $tier->id,
                    ]));
                }
            }

            return $tier->load('approvers');
        });
    }

    /**
     * Get the accrual schedule for a leave policy.
     */
    public function getAccrualSchedule(LeavePolicy $policy): array
    {
        $leaveTypes = $policy->leaveTypes()
            ->with(['leaveTiers' => function ($q) {
                $q->active()->byPriority();
            }])
            ->active()
            ->get();

        $schedule = [];

        foreach ($leaveTypes as $leaveType) {
            $typeSchedule = [
                'leave_type_id' => $leaveType->id,
                'name' => $leaveType->name,
                'code' => $leaveType->code,
                'accrual_type' => $leaveType->accrual_type,
                'accrual_day' => $leaveType->accrual_day,
                'tiers' => [],
            ];

            foreach ($leaveType->leaveTiers as $tier) {
                $typeSchedule['tiers'][] = [
                    'tier_id' => $tier->id,
                    'name' => $tier->name,
                    'entitled_days' => $tier->entitled_days,
                    'entitlement_period' => $tier->entitlement_period,
                    'monthly_accrual_rate' => $tier->monthly_accrual_rate,
                    'min_service_months' => $tier->min_service_months,
                    'max_service_months' => $tier->max_service_months,
                ];
            }

            $schedule[] = $typeSchedule;
        }

        return $schedule;
    }
}
