<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Concerns\SupportsAgGrid;
use App\Http\Controllers\Controller;
use App\Http\Resources\HR\EmployeeResource;
use App\Models\HR\Employee;
use App\Models\HR\SalaryStructure;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use SupportsAgGrid;
    public function __construct(
        private EmployeeService $employeeService
    ) {
    }

    /**
     * List employees with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'designation', 'branch'])
            ->when($request->status, fn($q, $status) => $q->where('employment_status', $status))
            ->when($request->department_id, fn($q, $id) => $q->inDepartment($id))
            ->when($request->designation_id, fn($q, $id) => $q->withDesignation($id))
            ->when($request->employment_type, fn($q, $type) => $q->where('employment_type', $type))
            ->when($request->active === 'true', fn($q) => $q->active())
            ->when($request->on_probation === 'true', fn($q) => $q->onProbation())
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['first_name', 'last_name', 'email', 'joining_date', 'employment_status', 'created_at', 'updated_at'], 'first_name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($query, $request);
        }

        $employees = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($employees, EmployeeResource::class);
    }

    /**
     * Store a new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'nationality' => 'nullable|string|max:50',
            'email' => [
                'required',
                'email',
                'max:200',
                Rule::unique('employees', 'email')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'personal_email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'address_line_1' => 'nullable|string|max:200',
            'address_line_2' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'department_id' => ['required', Rule::exists('departments', 'id')->where('organization_id', auth()->user()->organization_id)],
            'designation_id' => ['required', Rule::exists('designations', 'id')->where('organization_id', auth()->user()->organization_id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('organization_id', auth()->user()->organization_id)],
            'reporting_manager_id' => ['nullable', Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id)],
            'joining_date' => 'required|date',
            'employment_type' => 'nullable|in:full_time,part_time,contract,intern,probation',
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry' => 'nullable|date',
            'tax_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_ifsc_code' => 'nullable|string|max:20',
            'bank_iban' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $employee = $this->employeeService->create($validated);

        return $this->created(new EmployeeResource($employee), 'Employee created successfully.');
    }

    /**
     * Show a specific employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        return $this->success(new EmployeeResource(
            $employee->load([
                'department',
                'designation',
                'branch',
                'reportingManager',
                'currentSalary.salaryStructure',
                'documents',
                'qualifications',
                'experiences',
            ])
        ));
    }

    /**
     * Update an employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'employee_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_number')
                    ->where('organization_id', auth()->user()->organization_id)
                    ->ignore($employee->id),
            ],
            'first_name' => 'sometimes|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'nationality' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:200',
            'personal_email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'address_line_1' => 'nullable|string|max:200',
            'address_line_2' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', auth()->user()->organization_id)],
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('organization_id', auth()->user()->organization_id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('organization_id', auth()->user()->organization_id)],
            'reporting_manager_id' => ['nullable', Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id)],
            'national_id' => 'nullable|string|max:50',
            'passport_number' => 'nullable|string|max:50',
            'passport_expiry' => 'nullable|date',
            'tax_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_ifsc_code' => 'nullable|string|max:20',
            'bank_iban' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $employee = $this->employeeService->update($employee, $validated);

        return $this->success(new EmployeeResource($employee), 'Employee updated successfully.');
    }

    /**
     * Deactivate (soft delete) an employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        try {
            $this->employeeService->terminate($employee, now(), 'deactivated', 'terminated');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 'EMPLOYEE_TERMINATE_FAILED', 422);
        }

        return $this->success(null, 'Employee deactivated successfully.');
    }

    /**
     * Assign salary to employee.
     */
    public function assignSalary(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'salary_structure_id' => 'required|exists:salary_structures,id',
            'effective_from' => 'required|date',
            'components' => 'required|array',
            'components.*' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $structure = SalaryStructure::findOrFail($validated['salary_structure_id']);

        $salary = $this->employeeService->assignSalary(
            $employee,
            $structure,
            $validated['components'],
            new \DateTime($validated['effective_from']),
            $validated['reason'] ?? null
        );

        return $this->success($salary, 'Salary assigned successfully.');
    }

    /**
     * Confirm employee (end probation).
     */
    public function confirm(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_date' => 'nullable|date',
        ]);

        $employee = $this->employeeService->confirm(
            $employee,
            isset($validated['confirmation_date']) ? new \DateTime($validated['confirmation_date']) : null
        );

        return $this->success(new EmployeeResource($employee), 'Employee confirmed successfully.');
    }

    /**
     * Terminate employee.
     */
    public function terminate(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'termination_date' => 'required|date',
            'reason' => 'required|string|max:500',
            'status' => 'nullable|in:terminated,resigned,absconded',
        ]);

        $employee = $this->employeeService->terminate(
            $employee,
            new \DateTime($validated['termination_date']),
            $validated['reason'],
            $validated['status'] ?? 'terminated'
        );

        return $this->success(new EmployeeResource($employee), 'Employee terminated successfully.');
    }

    /**
     * Reactivate a terminated employee (rehire workflow).
     */
    public function reactivate(Employee $employee, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rehire_date' => 'required|date|after:today',
            'notes'       => 'nullable|string',
        ]);

        try {
            $employee = $this->employeeService->reactivate($employee, $validated);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 'EMPLOYEE_REACTIVATE_FAILED', 422);
        }

        return $this->success(new EmployeeResource($employee), 'Employee reactivated successfully.');
    }

    /**
     * Get employee statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->employeeService->getStatistics();

        return $this->success($stats);
    }

    /**
     * Get employees with expiring documents.
     */
    public function expiringDocuments(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('hr.employees.view'), 403);

        $days = (int) ($request->days ?? 30);
        $documents = $this->employeeService->getExpiringDocuments($days);

        return $this->success($documents);
    }
}
