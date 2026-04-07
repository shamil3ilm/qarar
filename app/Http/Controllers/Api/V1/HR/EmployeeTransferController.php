<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeTransfer;
use App\Services\HR\EmployeeTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeTransferController extends Controller
{
    public function __construct(
        private readonly EmployeeTransferService $transferService
    ) {}

    /**
     * List all transfers for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = $this->transferService->list($request->only([
            'employee_id',
            'status',
            'transfer_type',
            'effective_date_from',
            'effective_date_to',
            'per_page',
        ]));

        return $this->paginated($transfers);
    }

    /**
     * Initiate a new transfer.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'employee_id'           => ['required', Rule::exists('employees', 'id')->where('organization_id', $orgId)],
            'effective_date'        => 'required|date',
            'transfer_type'         => ['required', Rule::in([
                EmployeeTransfer::TYPE_DEPARTMENT,
                EmployeeTransfer::TYPE_POSITION,
                EmployeeTransfer::TYPE_DESIGNATION,
                EmployeeTransfer::TYPE_LOCATION,
                EmployeeTransfer::TYPE_MANAGER,
                EmployeeTransfer::TYPE_LATERAL,
                EmployeeTransfer::TYPE_PROMOTION,
                EmployeeTransfer::TYPE_DEMOTION,
            ])],
            'reason'                  => 'nullable|string|max:500',
            'to_department_id'        => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $orgId)],
            'to_designation_id'       => ['nullable', Rule::exists('designations', 'id')->where('organization_id', $orgId)],
            'to_position_id'          => ['nullable', Rule::exists('positions', 'id')->where('organization_id', $orgId)],
            'to_reporting_manager_id' => ['nullable', Rule::exists('employees', 'id')->where('organization_id', $orgId)],
            'to_branch_id'            => ['nullable', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'notes'                   => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $transfer = $this->transferService->initiate($employee, $validated);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'TRANSFER_INITIATE_FAILED', 422);
        }

        return $this->created($transfer->load([
            'employee',
            'fromDepartment',
            'toDepartment',
            'fromDesignation',
            'toDesignation',
        ]), 'Employee transfer initiated successfully.');
    }

    /**
     * Show a specific transfer.
     */
    public function show(EmployeeTransfer $employeeTransfer): JsonResponse
    {
        return $this->success($employeeTransfer->load([
            'employee',
            'fromDepartment',
            'toDepartment',
            'fromDesignation',
            'toDesignation',
            'fromPosition',
            'toPosition',
            'fromManager',
            'toManager',
            'fromBranch',
            'toBranch',
            'initiatedBy',
            'approvedBy',
            'rejectedBy',
        ]));
    }

    /**
     * Cancel / soft-delete a transfer (draft or pending only).
     */
    public function destroy(EmployeeTransfer $employeeTransfer): JsonResponse
    {
        try {
            $this->transferService->cancel($employeeTransfer);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'TRANSFER_CANCEL_FAILED', 422);
        }

        return $this->success(null, 'Employee transfer cancelled successfully.');
    }

    /**
     * Approve a pending transfer.
     */
    public function approve(EmployeeTransfer $employeeTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->approve($employeeTransfer, auth()->user());
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'TRANSFER_APPROVE_FAILED', 422);
        }

        return $this->success($transfer, 'Employee transfer approved successfully.');
    }

    /**
     * Reject a pending transfer.
     */
    public function reject(Request $request, EmployeeTransfer $employeeTransfer): JsonResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $transfer = $this->transferService->reject(
                $employeeTransfer,
                auth()->user(),
                $validated['rejection_reason']
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'TRANSFER_REJECT_FAILED', 422);
        }

        return $this->success($transfer, 'Employee transfer rejected successfully.');
    }

    /**
     * Apply an approved transfer to the employee record.
     */
    public function apply(EmployeeTransfer $employeeTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->apply($employeeTransfer);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'TRANSFER_APPLY_FAILED', 422);
        }

        return $this->success($transfer->load('employee'), 'Employee transfer applied successfully.');
    }

    /**
     * Return all transfers for a specific employee (filtered by employee_id query param).
     */
    public function employeeHistory(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('organization_id', $orgId)],
        ]);

        $transfers = $this->transferService->list(array_merge(
            $request->only(['status', 'per_page']),
            ['employee_id' => $validated['employee_id']]
        ));

        return $this->paginated($transfers);
    }
}
