<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDependent;
use App\Services\HR\EmployeeDependentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeDependentController extends Controller
{
    public function __construct(
        private readonly EmployeeDependentService $dependentService
    ) {}

    /**
     * List all dependents for an employee.
     */
    public function index(Employee $employee): JsonResponse
    {
        $dependents = $this->dependentService->list($employee);

        return $this->success($dependents);
    }

    /**
     * Create a new dependent for an employee.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'relationship'   => ['required', Rule::in([
                EmployeeDependent::RELATIONSHIP_SPOUSE,
                EmployeeDependent::RELATIONSHIP_CHILD,
                EmployeeDependent::RELATIONSHIP_PARENT,
                EmployeeDependent::RELATIONSHIP_SIBLING,
                EmployeeDependent::RELATIONSHIP_OTHER,
            ])],
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'date_of_birth'  => 'nullable|date|before:today',
            'gender'         => 'nullable|in:male,female,other',
            'nationality'    => 'nullable|string|size:2',
            'id_type'        => 'nullable|in:national_id,passport,birth_certificate',
            'id_number'      => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
            'is_beneficiary' => 'nullable|boolean',
            'is_sponsored'   => 'nullable|boolean',
            'visa_number'    => 'nullable|string|max:50',
            'visa_expiry_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        $dependent = $this->dependentService->create($employee, $validated);

        return $this->created($dependent, 'Dependent created successfully.');
    }

    /**
     * Show a specific dependent — verifies ownership via route model binding scoping.
     */
    public function show(Employee $employee, EmployeeDependent $dependent): JsonResponse
    {
        $this->abortIfNotOwned($employee, $dependent);

        return $this->success($dependent);
    }

    /**
     * Update a dependent.
     */
    public function update(Request $request, Employee $employee, EmployeeDependent $dependent): JsonResponse
    {
        $this->abortIfNotOwned($employee, $dependent);

        $validated = $request->validate([
            'relationship'   => ['sometimes', Rule::in([
                EmployeeDependent::RELATIONSHIP_SPOUSE,
                EmployeeDependent::RELATIONSHIP_CHILD,
                EmployeeDependent::RELATIONSHIP_PARENT,
                EmployeeDependent::RELATIONSHIP_SIBLING,
                EmployeeDependent::RELATIONSHIP_OTHER,
            ])],
            'first_name'     => 'sometimes|string|max:100',
            'last_name'      => 'sometimes|string|max:100',
            'date_of_birth'  => 'nullable|date|before:today',
            'gender'         => 'nullable|in:male,female,other',
            'nationality'    => 'nullable|string|size:2',
            'id_type'        => 'nullable|in:national_id,passport,birth_certificate',
            'id_number'      => 'nullable|string|max:100',
            'id_expiry_date' => 'nullable|date',
            'is_beneficiary' => 'nullable|boolean',
            'is_sponsored'   => 'nullable|boolean',
            'visa_number'    => 'nullable|string|max:50',
            'visa_expiry_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        $dependent = $this->dependentService->update($dependent, $validated);

        return $this->success($dependent, 'Dependent updated successfully.');
    }

    /**
     * Delete a dependent.
     */
    public function destroy(Employee $employee, EmployeeDependent $dependent): JsonResponse
    {
        $this->abortIfNotOwned($employee, $dependent);

        $this->dependentService->delete($dependent);

        return $this->success(null, 'Dependent deleted successfully.');
    }

    /**
     * Ensure the dependent belongs to the employee from the route.
     */
    private function abortIfNotOwned(Employee $employee, EmployeeDependent $dependent): void
    {
        if ($dependent->employee_id !== $employee->id) {
            abort(404);
        }
    }
}
