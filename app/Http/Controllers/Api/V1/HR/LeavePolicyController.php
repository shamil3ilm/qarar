<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\Leave\LeaveType;
use App\Services\HR\LeavePolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function __construct(
        private LeavePolicyService $policyService
    ) {}

    /**
     * List leave policies.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeavePolicy::query()
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('name');

        $policies = $request->per_page
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        if ($request->per_page) {
            return $this->paginated($policies);
        }

        return $this->success($policies);
    }

    /**
     * Create a new leave policy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'policy_year_type' => 'nullable|in:calendar,fiscal,anniversary',
            'year_start_date' => 'nullable|date',
            'allow_negative_balance' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'min_notice_days' => 'nullable|integer|min:0|max:255',
            'allow_half_day' => 'nullable|boolean',
            'allow_hourly' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $policy = $this->policyService->create($validated);

        return $this->created($policy);
    }

    /**
     * Show a leave policy.
     */
    public function show(LeavePolicy $leavePolicy): JsonResponse
    {
        return $this->success(
            $leavePolicy->load(['leaveTypes.leaveTiers.approvers'])
        );
    }

    /**
     * Update a leave policy.
     */
    public function update(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'policy_year_type' => 'nullable|in:calendar,fiscal,anniversary',
            'year_start_date' => 'nullable|date',
            'allow_negative_balance' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'min_notice_days' => 'nullable|integer|min:0|max:255',
            'allow_half_day' => 'nullable|boolean',
            'allow_hourly' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $policy = $this->policyService->update($leavePolicy, $validated);

        return $this->success($policy);
    }

    /**
     * Delete a leave policy.
     */
    public function destroy(LeavePolicy $leavePolicy): JsonResponse
    {
        $leavePolicy->delete();

        return $this->success(null, 'Leave policy deleted successfully.');
    }

    /**
     * List leave types for a policy.
     */
    public function leaveTypes(LeavePolicy $leavePolicy): JsonResponse
    {
        $types = $leavePolicy->leaveTypes()
            ->with('leaveTiers')
            ->active()
            ->ordered()
            ->get();

        return $this->success($types);
    }

    /**
     * Create a leave type within a policy.
     */
    public function storeLeaveType(Request $request, LeavePolicy $leavePolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string',
            'is_paid' => 'nullable|boolean',
            'is_encashable' => 'nullable|boolean',
            'is_carryforward_allowed' => 'nullable|boolean',
            'max_carryforward_days' => 'nullable|integer|min:0',
            'requires_attachment' => 'nullable|boolean',
            'requires_reason' => 'nullable|boolean',
            'gender_restriction' => 'nullable|in:male,female',
            'employment_type_restriction' => 'nullable|string|max:50',
            'min_service_months' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'min_days_per_request' => 'nullable|integer|min:1',
            'max_days_per_request' => 'nullable|integer|min:1',
            'allowed_days_of_week' => 'nullable|array',
            'blackout_dates' => 'nullable|array',
            'accrual_type' => 'nullable|in:yearly,monthly,weekly,none',
            'accrual_day' => 'nullable|integer|min:1|max:31',
            'count_holidays' => 'nullable|boolean',
            'count_weekends' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['leave_policy_id'] = $leavePolicy->id;

        $leaveType = LeaveType::create($validated);

        return $this->created($leaveType);
    }

    /**
     * Assign a tier to a leave type.
     */
    public function assignTier(Request $request, LeaveType $leaveType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_service_months' => 'nullable|integer|min:0',
            'max_service_months' => 'nullable|integer|min:0',
            'employee_grade' => 'nullable|string',
            'department_id' => 'nullable|string',
            'entitled_days' => 'required|numeric|min:0',
            'entitlement_period' => 'nullable|in:yearly,monthly',
            'monthly_accrual_rate' => 'nullable|numeric|min:0',
            'max_carryforward_days' => 'nullable|integer|min:0',
            'carryforward_expiry_months' => 'nullable|integer|min:0',
            'max_encashable_days' => 'nullable|integer|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:0',
            'approvers' => 'nullable|array',
            'approvers.*.user_id' => 'nullable|exists:users,id',
            'approvers.*.role_id' => 'nullable|exists:roles,id',
            'approvers.*.designation' => 'nullable|string',
            'approvers.*.approval_level' => 'nullable|integer|min:1',
            'approvers.*.can_approve' => 'nullable|boolean',
            'approvers.*.can_reject' => 'nullable|boolean',
            'approvers.*.is_final_approver' => 'nullable|boolean',
        ]);

        $tier = $this->policyService->assignTier($leaveType, $validated);

        return $this->created($tier);
    }

    /**
     * Get accrual schedule for a policy.
     */
    public function accrualSchedule(LeavePolicy $leavePolicy): JsonResponse
    {
        $schedule = $this->policyService->getAccrualSchedule($leavePolicy);

        return $this->success($schedule);
    }

    /**
     * Update a leave tier.
     */
    public function updateTier(Request $request, LeaveTier $leaveTier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'min_service_months' => 'nullable|integer|min:0',
            'max_service_months' => 'nullable|integer|min:0',
            'employee_grade' => 'nullable|string',
            'department_id' => 'nullable|string',
            'entitled_days' => 'sometimes|numeric|min:0',
            'entitlement_period' => 'nullable|in:yearly,monthly',
            'monthly_accrual_rate' => 'nullable|numeric|min:0',
            'max_carryforward_days' => 'nullable|integer|min:0',
            'carryforward_expiry_months' => 'nullable|integer|min:0',
            'max_encashable_days' => 'nullable|integer|min:0',
            'encashment_rate' => 'nullable|numeric|min:0|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $leaveTier->update($validated);

        return $this->success($leaveTier->fresh('approvers'));
    }

    /**
     * Delete a leave tier.
     */
    public function destroyTier(LeaveTier $leaveTier): JsonResponse
    {
        $leaveTier->delete();

        return $this->success(null, 'Leave tier deleted successfully.');
    }
}
