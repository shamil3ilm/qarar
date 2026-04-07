<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\BenefitType;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeBenefit;
use App\Services\HR\BenefitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenefitsController extends Controller
{
    public function __construct(
        private BenefitsService $benefitsService
    ) {}

    /**
     * List benefit types.
     */
    public function index(Request $request): JsonResponse
    {
        $types = BenefitType::query()
            ->when($request->category, fn($q, $v) => $q->byCategory($v))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('category')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($types);
    }

    /**
     * Create a benefit type.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:30',
            'category' => 'required|in:allowance,insurance,other',
            'calculation_type' => 'required|in:fixed,percentage',
            'default_amount' => 'numeric|min:0',
            'percentage_basis' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'eligibility_rules' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
        ]);

        $type = BenefitType::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'is_active' => true,
        ]));

        return $this->created($type, 'Benefit type created successfully.');
    }

    /**
     * Show a benefit type.
     */
    public function showType(BenefitType $benefitType): JsonResponse
    {
        return $this->success($benefitType);
    }

    /**
     * Update a benefit type.
     */
    public function updateType(Request $request, BenefitType $benefitType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'calculation_type' => 'in:fixed,percentage',
            'default_amount' => 'numeric|min:0',
            'percentage_basis' => 'nullable|numeric|min:0|max:100',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'eligibility_rules' => 'nullable|array',
            'description' => 'nullable|string|max:1000',
        ]);

        $benefitType->update($validated);

        return $this->success($benefitType->fresh(), 'Benefit type updated successfully.');
    }

    /**
     * List benefits for an employee.
     */
    public function listEmployeeBenefits(Request $request, Employee $employee): JsonResponse
    {
        $benefits = EmployeeBenefit::forEmployee($employee->id)
            ->with('benefitType')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->benefit_type_id, fn($q, $v) => $q->where('benefit_type_id', $v))
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($benefits);
    }

    /**
     * Enroll an employee in a benefit.
     */
    public function enroll(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'benefit_type_id' => 'required|exists:benefit_types,id',
            'amount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'policy_number' => 'nullable|string|max:100',
            'provider_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $benefitType = BenefitType::findOrFail($validated['benefit_type_id']);

        try {
            $benefit = $this->benefitsService->enrollBenefit($employee, $benefitType, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($benefit, 'Employee enrolled in benefit successfully.');
    }

    /**
     * Update an employee benefit.
     */
    public function update(Request $request, EmployeeBenefit $employeeBenefit): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'end_date' => 'nullable|date',
            'policy_number' => 'nullable|string|max:100',
            'provider_name' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->tryAction(
            fn() => $this->benefitsService->updateBenefit($employeeBenefit, $validated),
            'Benefit updated successfully.'
        );
    }

    /**
     * Terminate an employee benefit.
     */
    public function terminate(Request $request, EmployeeBenefit $employeeBenefit): JsonResponse
    {
        $validated = $request->validate([
            'end_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ]);

        return $this->tryAction(
            fn() => $this->benefitsService->terminateBenefit(
                $employeeBenefit,
                $validated['end_date'] ?? null,
                $validated['reason'] ?? null
            ),
            'Benefit terminated successfully.'
        );
    }

    /**
     * Show benefit change history.
     */
    public function changeHistory(EmployeeBenefit $employeeBenefit): JsonResponse
    {
        $changes = $employeeBenefit->changes()->with('changedBy')->orderByDesc('changed_at')->get();

        return $this->success($changes);
    }
}
