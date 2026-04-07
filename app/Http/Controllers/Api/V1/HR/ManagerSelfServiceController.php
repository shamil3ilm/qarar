<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\ManagerDelegation;
use App\Services\HR\ManagerSelfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerSelfServiceController extends Controller
{
    public function __construct(
        private readonly ManagerSelfServiceService $service,
    ) {}

    /**
     * GET /api/v1/hr/manager/team
     */
    public function team(Request $request): JsonResponse
    {
        $includeIndirect = $request->boolean('include_indirect', false);
        $managerId       = (int) auth()->id();

        $team = $this->service->getTeam($managerId, $includeIndirect);

        return $this->success($team);
    }

    /**
     * GET /api/v1/hr/manager/pending-approvals
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $managerId = (int) auth()->id();
        $approvals = $this->service->getPendingApprovals($managerId);

        return $this->success($approvals);
    }

    /**
     * GET /api/v1/hr/manager/team-attendance?date=YYYY-MM-DD
     */
    public function teamAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $managerId = (int) auth()->id();
        $date      = $request->input('date', now()->toDateString());

        $attendance = $this->service->getTeamAttendance($managerId, $date);

        return $this->success($attendance);
    }

    /**
     * GET /api/v1/hr/manager/team-leave-calendar?month=YYYY-MM
     */
    public function teamLeaveCalendar(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $managerId = (int) auth()->id();
        $month     = $request->input('month', now()->format('Y-m'));

        $calendar = $this->service->getTeamLeaveCalendar($managerId, $month);

        return $this->success($calendar);
    }

    /**
     * GET /api/v1/hr/manager/delegations
     */
    public function delegations(Request $request): JsonResponse
    {
        $managerId   = (int) auth()->id();
        $delegations = ManagerDelegation::query()
            ->with(['delegate'])
            ->forManager($managerId)
            ->orderBy('valid_from', 'desc')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($delegations);
    }

    /**
     * POST /api/v1/hr/manager/delegations
     */
    public function createDelegation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delegate_id'     => 'required|integer|exists:users,id',
            'delegation_type' => 'required|in:full,leave_approval,attendance_approval,expense_approval',
            'valid_from'      => 'required|date',
            'valid_to'        => 'nullable|date|after_or_equal:valid_from',
            'reason'          => 'nullable|string',
        ]);

        $validated['manager_id'] = auth()->id();

        try {
            $delegation = $this->service->createDelegation($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($delegation->load('delegate'), 'Delegation created.');
    }

    /**
     * DELETE /api/v1/hr/manager/delegations/{delegationId}
     */
    public function revokeDelegation(string $delegationId): JsonResponse
    {
        $managerId  = (int) auth()->id();
        $delegation = ManagerDelegation::where('manager_id', $managerId)->findOrFail($delegationId);

        $delegation = $this->service->revokeDelegation($delegation);

        return $this->success($delegation, 'Delegation revoked.');
    }
}
