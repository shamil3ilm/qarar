<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave\LeaveAccrual;
use App\Models\HR\Leave\LeaveAdjustment;
use App\Models\HR\Leave\LeaveEncashment;
use App\Services\HR\LeaveAccrualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveAccrualController extends Controller
{
    public function __construct(
        private LeaveAccrualService $accrualService
    ) {}

    /**
     * Process accruals for the organization.
     */
    public function processAccruals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accrual_date' => 'nullable|date',
        ]);

        $organizationId = $this->organizationId($request);
        $processed = $this->accrualService->processAccruals(
            $organizationId,
            $validated['accrual_date'] ?? null
        );

        return $this->success(
            ['processed_count' => $processed],
            "Processed accruals for {$processed} employee-leave type combinations."
        );
    }

    /**
     * Get accrual history for a balance.
     */
    public function accrualHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $balance = $this->accrualService->getBalance(
            (int) $validated['employee_id'],
            (int) $validated['leave_type_id'],
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        if (!$balance) {
            return $this->notFound('Leave balance not found.');
        }

        $accruals = LeaveAccrual::where('leave_balance_id', $balance->id)
            ->orderByDesc('accrual_date')
            ->get();

        return $this->success([
            'balance' => $balance,
            'accruals' => $accruals,
        ]);
    }

    /**
     * Adjust an employee's leave balance.
     */
    public function adjustBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'adjustment_type' => 'required|in:add,deduct,set,carryforward,encashment',
            'days' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'effective_date' => 'nullable|date',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by'] = auth()->id();
        $validated['approved_by'] = auth()->id();

        $adjustment = $this->accrualService->adjustBalance($validated);

        return $this->created($adjustment);
    }

    /**
     * List adjustments.
     */
    public function adjustments(Request $request): JsonResponse
    {
        $query = LeaveAdjustment::with(['employee', 'leaveType', 'creator', 'approver'])
            ->when($request->employee_id, fn($q, $id) => $q->forEmployee((int) $id))
            ->when($request->leave_type_id, fn($q, $id) => $q->where('leave_type_id', $id))
            ->orderByDesc('created_at');

        $adjustments = $request->per_page
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        if ($request->per_page) {
            return $this->paginated($adjustments);
        }

        return $this->success($adjustments);
    }

    /**
     * Request leave encashment.
     */
    public function encashLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'requested_days' => 'required|numeric|min:0.5',
            'daily_rate' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $validated['organization_id'] = $this->organizationId($request);
        $validated['created_by'] = auth()->id();

        $encashment = $this->accrualService->encashLeave($validated);

        return $this->created($encashment);
    }

    /**
     * List encashment requests.
     */
    public function encashments(Request $request): JsonResponse
    {
        $query = LeaveEncashment::with(['employee', 'leaveType', 'creator', 'approver'])
            ->when($request->employee_id, fn($q, $id) => $q->forEmployee((int) $id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        $encashments = $request->per_page
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        if ($request->per_page) {
            return $this->paginated($encashments);
        }

        return $this->success($encashments);
    }

    /**
     * Approve an encashment request.
     */
    public function approveEncashment(Request $request, LeaveEncashment $leaveEncashment): JsonResponse
    {
        $validated = $request->validate([
            'approved_days' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($leaveEncashment->status !== LeaveEncashment::STATUS_PENDING) {
            return $this->error('Only pending encashments can be approved.', 'INVALID_STATUS', 422);
        }

        $approvedDays = $validated['approved_days'] ?? $leaveEncashment->requested_days;
        $amount = round(
            (float) $approvedDays * (float) $leaveEncashment->daily_rate * ((float) $leaveEncashment->encashment_rate / 100),
            2
        );

        $leaveEncashment->update([
            'approved_days' => $approvedDays,
            'amount' => $amount,
            'status' => LeaveEncashment::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'notes' => $validated['notes'] ?? $leaveEncashment->notes,
        ]);

        return $this->success($leaveEncashment->fresh());
    }

    /**
     * Get employee leave balance.
     */
    public function getBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $balance = $this->accrualService->getBalance(
            (int) $validated['employee_id'],
            (int) $validated['leave_type_id'],
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        if (!$balance) {
            return $this->notFound('Leave balance not found.');
        }

        return $this->success($balance);
    }
}
