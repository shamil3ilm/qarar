<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeTransfer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeTransferService
{
    /**
     * List transfers for the authenticated user's organisation, with optional filters.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $orgId = auth()->user()->organization_id;

        return EmployeeTransfer::with([
            'employee',
            'fromDepartment',
            'toDepartment',
            'fromDesignation',
            'toDesignation',
            'initiatedBy',
        ])
            ->where('organization_id', $orgId)
            ->when(isset($filters['employee_id']), fn($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['transfer_type']), fn($q) => $q->where('transfer_type', $filters['transfer_type']))
            ->when(isset($filters['effective_date_from']), fn($q) => $q->where('effective_date', '>=', $filters['effective_date_from']))
            ->when(isset($filters['effective_date_to']), fn($q) => $q->where('effective_date', '<=', $filters['effective_date_to']))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Initiate a new employee transfer (status = pending_approval).
     */
    public function initiate(Employee $employee, array $data): EmployeeTransfer
    {
        $orgId = auth()->user()->organization_id;

        $transferNumber = $this->generateTransferNumber($orgId);

        return EmployeeTransfer::create([
            'organization_id'           => $orgId,
            'employee_id'               => $employee->id,
            'transfer_number'           => $transferNumber,
            'effective_date'            => $data['effective_date'],
            'transfer_type'             => $data['transfer_type'],
            'reason'                    => $data['reason'] ?? null,
            // Capture current state
            'from_department_id'        => $employee->department_id,
            'from_designation_id'       => $employee->designation_id,
            'from_reporting_manager_id' => $employee->reporting_manager_id,
            'from_branch_id'            => $employee->branch_id,
            'from_position_id'          => null, // position_id not on Employee model
            // Desired state from request
            'to_department_id'          => $data['to_department_id'] ?? null,
            'to_designation_id'         => $data['to_designation_id'] ?? null,
            'to_reporting_manager_id'   => $data['to_reporting_manager_id'] ?? null,
            'to_branch_id'              => $data['to_branch_id'] ?? null,
            'to_position_id'            => $data['to_position_id'] ?? null,
            'status'                    => EmployeeTransfer::STATUS_PENDING_APPROVAL,
            'initiated_by'              => auth()->id(),
            'notes'                     => $data['notes'] ?? null,
        ]);
    }

    /**
     * Approve a pending transfer.
     */
    public function approve(EmployeeTransfer $transfer, User $approver): EmployeeTransfer
    {
        if ($transfer->status !== EmployeeTransfer::STATUS_PENDING_APPROVAL) {
            throw new \DomainException('Only transfers with status pending_approval can be approved.');
        }

        $transfer->update([
            'status'      => EmployeeTransfer::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $transfer->fresh();
    }

    /**
     * Reject a pending transfer.
     */
    public function reject(EmployeeTransfer $transfer, User $approver, string $reason): EmployeeTransfer
    {
        if ($transfer->status !== EmployeeTransfer::STATUS_PENDING_APPROVAL) {
            throw new \DomainException('Only transfers with status pending_approval can be rejected.');
        }

        $transfer->update([
            'status'           => EmployeeTransfer::STATUS_REJECTED,
            'rejected_by'      => $approver->id,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $transfer->fresh();
    }

    /**
     * Apply an approved transfer — updates the Employee record inside a transaction.
     */
    public function apply(EmployeeTransfer $transfer): EmployeeTransfer
    {
        if ($transfer->status !== EmployeeTransfer::STATUS_APPROVED) {
            throw new \DomainException('Only transfers with status approved can be applied.');
        }

        return DB::transaction(function () use ($transfer): EmployeeTransfer {
            $employee = $transfer->employee;

            $updates = array_filter([
                'department_id'        => $transfer->to_department_id,
                'designation_id'       => $transfer->to_designation_id,
                'reporting_manager_id' => $transfer->to_reporting_manager_id,
                'branch_id'            => $transfer->to_branch_id,
            ], fn($v) => $v !== null);

            if (!empty($updates)) {
                $employee->update($updates);
            }

            $transfer->update([
                'status'     => EmployeeTransfer::STATUS_APPLIED,
                'applied_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }

    /**
     * Cancel a draft or pending transfer (soft delete).
     */
    public function cancel(EmployeeTransfer $transfer): void
    {
        $cancellable = [
            EmployeeTransfer::STATUS_DRAFT,
            EmployeeTransfer::STATUS_PENDING_APPROVAL,
        ];

        if (!in_array($transfer->status, $cancellable, true)) {
            throw new \DomainException('Only draft or pending_approval transfers can be cancelled.');
        }

        $transfer->delete();
    }

    private function generateTransferNumber(int $orgId): string
    {
        $prefix = 'TRF-' . date('Ymd') . '-';

        $seq = EmployeeTransfer::withTrashed()
            ->where('organization_id', $orgId)
            ->where('transfer_number', 'like', $prefix . '%')
            ->count() + 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
