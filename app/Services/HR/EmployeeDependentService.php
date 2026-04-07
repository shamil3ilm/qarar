<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeDependent;
use Illuminate\Database\Eloquent\Collection;

class EmployeeDependentService
{
    /**
     * List all dependents for an employee.
     */
    public function list(Employee $employee): Collection
    {
        return $employee->dependents()->get();
    }

    /**
     * Create a new dependent for an employee.
     */
    public function create(Employee $employee, array $data): EmployeeDependent
    {
        return EmployeeDependent::create([
            'organization_id' => $employee->organization_id,
            'employee_id'     => $employee->id,
            'relationship'    => $data['relationship'],
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'date_of_birth'   => $data['date_of_birth'] ?? null,
            'gender'          => $data['gender'] ?? null,
            'nationality'     => $data['nationality'] ?? null,
            'id_type'         => $data['id_type'] ?? null,
            'id_number'       => $data['id_number'] ?? null,
            'id_expiry_date'  => $data['id_expiry_date'] ?? null,
            'is_beneficiary'  => $data['is_beneficiary'] ?? false,
            'is_sponsored'    => $data['is_sponsored'] ?? false,
            'visa_number'     => $data['visa_number'] ?? null,
            'visa_expiry_date' => $data['visa_expiry_date'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update a dependent.
     */
    public function update(EmployeeDependent $dependent, array $data): EmployeeDependent
    {
        $dependent->update(array_intersect_key($data, array_flip([
            'relationship',
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'nationality',
            'id_type',
            'id_number',
            'id_expiry_date',
            'is_beneficiary',
            'is_sponsored',
            'visa_number',
            'visa_expiry_date',
            'notes',
        ])));

        return $dependent->fresh();
    }

    /**
     * Soft-delete a dependent.
     */
    public function delete(EmployeeDependent $dependent): void
    {
        $dependent->delete();
    }

    /**
     * Return only beneficiary dependents for insurance / EOSB calculations.
     */
    public function getBeneficiaries(Employee $employee): Collection
    {
        return $employee->dependents()->beneficiaries()->get();
    }
}
