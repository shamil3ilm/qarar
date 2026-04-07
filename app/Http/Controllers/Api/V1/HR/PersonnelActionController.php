<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PersonnelAction;
use App\Services\HR\PersonnelActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personnel Actions Controller — SAP PA40.
 *
 * POST   /hr/personnel-actions                       initiate
 * GET    /hr/personnel-actions                       index
 * GET    /hr/personnel-actions/{id}                  show
 * POST   /hr/personnel-actions/{id}/submit           → submitted
 * POST   /hr/personnel-actions/{id}/approve          → approved + executed
 * POST   /hr/personnel-actions/{id}/reject           → rejected
 * POST   /hr/personnel-actions/{id}/reverse          → reversed
 */
class PersonnelActionController extends Controller
{
    public function __construct(
        private readonly PersonnelActionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PersonnelAction::with(['employee:id,first_name,last_name,employee_number', 'initiator:id,name'])
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->filled('employee_id'), fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('action_type'), fn($q) => $q->where('action_type', $request->action_type))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status));

        return $this->successResponse($query->latest()->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => 'required|integer|exists:employees,id',
            'action_type'    => 'required|string|in:hire,rehire,transfer,promotion,demotion,exit,leave_of_absence',
            'effective_date' => 'required|date',
            'payload'        => 'nullable|array',
            'reason'         => 'nullable|string|max:500',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $action = $this->service->initiate($validated, $request->user());

        return $this->successResponse($action->load('steps'), 'Personnel action initiated', 201);
    }

    public function show(string $id): JsonResponse
    {
        $action = PersonnelAction::with(['employee', 'initiator:id,name', 'approver:id,name', 'steps'])->findOrFail($id);

        return $this->successResponse($action);
    }

    public function submit(string $id): JsonResponse
    {
        $action  = PersonnelAction::findOrFail($id);
        $updated = $this->service->submit($action);

        return $this->successResponse($updated, 'Personnel action submitted for approval');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $action  = PersonnelAction::findOrFail($id);
        $updated = $this->service->approve($action, $request->user());

        return $this->successResponse($updated->load('steps'), 'Personnel action approved and executed');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $action  = PersonnelAction::findOrFail($id);
        $updated = $this->service->reject($action, $request->user(), $validated['reason']);

        return $this->successResponse($updated, 'Personnel action rejected');
    }

    public function reverse(Request $request, string $id): JsonResponse
    {
        $action  = PersonnelAction::findOrFail($id);
        $updated = $this->service->reverse($action, $request->user());

        return $this->successResponse($updated, 'Personnel action reversed');
    }
}
