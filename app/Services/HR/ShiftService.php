<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeShiftAssignment;
use App\Models\HR\Shift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * Assign a shift to an employee, closing any currently open assignment first.
     */
    public function assignShift(Employee $employee, Shift $shift, array $data): EmployeeShiftAssignment
    {
        if ($shift->organization_id !== $employee->organization_id) {
            throw new \InvalidArgumentException('Shift and employee must belong to the same organization.');
        }

        $effectiveFrom = $data['effective_from'] ?? now()->toDateString();

        return DB::transaction(function () use ($employee, $shift, $effectiveFrom, $data) {
            $effectiveTo = $data['effective_to'] ?? null;

            // Lock and check for any overlapping assignment for this employee
            $overlap = EmployeeShiftAssignment::where('employee_id', $employee->id)
                ->where(function ($query) use ($effectiveFrom, $effectiveTo) {
                    $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
                        // Existing assignment starts within the new range
                        $q->where('effective_from', '>=', $effectiveFrom);
                        if ($effectiveTo !== null) {
                            $q->where('effective_from', '<=', $effectiveTo);
                        }
                    })->orWhere(function ($q) use ($effectiveFrom, $effectiveTo) {
                        // Existing open assignment that would overlap
                        $q->where('effective_from', '<=', $effectiveFrom)
                            ->where(function ($q2) use ($effectiveFrom) {
                                $q2->whereNull('effective_to')
                                    ->orWhere('effective_to', '>=', $effectiveFrom);
                            });
                    });
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new \RuntimeException('Employee already has a shift assignment overlapping this period.');
            }

            // Close any existing open assignment that overlaps
            EmployeeShiftAssignment::where('employee_id', $employee->id)
                ->whereNull('effective_to')
                ->where('effective_from', '<=', $effectiveFrom)
                ->update(['effective_to' => $effectiveFrom]);

            return EmployeeShiftAssignment::create([
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'effective_from' => $effectiveFrom,
                'effective_to' => $data['effective_to'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Retrieve the currently active shift for an employee (null if none).
     */
    public function getActiveShift(Employee $employee): ?Shift
    {
        $assignment = EmployeeShiftAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('organization_id', $employee->organization_id)
            ->current()
            ->latest('effective_from')
            ->first();

        return $assignment?->shift;
    }
}
