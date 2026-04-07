<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\EosbPolicy;
use App\Models\HR\EosbProvision;
use App\Models\HR\EosbSettlement;
use App\Services\HR\EosbService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EosbController extends Controller
{
    public function __construct(
        private EosbService $eosbService
    ) {}

    /**
     * List EOSB policies for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $policies = EosbPolicy::query()
            ->when($request->country_code, fn($q, $v) => $q->where('country_code', $v))
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderBy('country_code')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($policies);
    }

    /**
     * Create an EOSB policy.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => 'required|string|max:10',
            'calculation_method' => 'required|in:saudi,uae,qatar,kuwait,bahrain,oman,india',
            'min_service_months' => 'integer|min:1|max:120',
            'first_period_days_per_year' => 'numeric|min:0|max:365',
            'first_period_years' => 'integer|min:1|max:20',
            'subsequent_days_per_year' => 'numeric|min:0|max:365',
            'prorate_partial_year' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $policy = EosbPolicy::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'is_active' => true,
        ]));

        return $this->created($policy, 'EOSB policy created successfully.');
    }

    /**
     * Show a specific EOSB policy.
     */
    public function show(EosbPolicy $eosbPolicy): JsonResponse
    {
        return $this->success($eosbPolicy);
    }

    /**
     * Update an EOSB policy.
     */
    public function update(Request $request, EosbPolicy $eosbPolicy): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => 'string|max:10',
            'calculation_method' => 'in:saudi,uae,qatar,kuwait,bahrain,oman,india',
            'min_service_months' => 'integer|min:1|max:120',
            'first_period_days_per_year' => 'numeric|min:0|max:365',
            'first_period_years' => 'integer|min:1|max:20',
            'subsequent_days_per_year' => 'numeric|min:0|max:365',
            'prorate_partial_year' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $eosbPolicy->update($validated);

        return $this->success($eosbPolicy->fresh(), 'EOSB policy updated successfully.');
    }

    /**
     * List EOSB provisions for an employee.
     */
    public function showEmployeeProvisions(Request $request, Employee $employee): JsonResponse
    {
        $provisions = EosbProvision::forEmployee($employee->id)
            ->when($request->year, fn($q, $y) => $q->where('period_year', $y))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($request->integer('per_page', 24));

        return $this->paginated($provisions);
    }

    /**
     * Calculate a settlement preview (without saving).
     */
    public function calculateSettlement(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'termination_date' => 'required|date',
        ]);

        return $this->tryAction(
            fn() => $this->eosbService->calculateFinalSettlement(
                $employee,
                Carbon::parse($validated['termination_date'])
            )
        );
    }

    /**
     * Create and save a settlement record.
     */
    public function storeSettlement(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'termination_date' => 'required|date',
            'deductions' => 'numeric|min:0',
            'currency_code' => 'string|size:3',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $settlement = $this->eosbService->generateSettlement($employee, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($settlement->load('employee'), 'EOSB settlement created successfully.');
    }

    /**
     * List settlements.
     */
    public function settlements(Request $request): JsonResponse
    {
        $settlements = EosbSettlement::with('employee')
            ->when($request->employee_id, fn($q, $v) => $q->where('employee_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($settlements);
    }

    /**
     * Approve a settlement.
     */
    public function approveSettlement(EosbSettlement $settlement): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->eosbService->approveSettlement($settlement),
            'Settlement approved.'
        );
    }

    /**
     * Mark a settlement as paid.
     */
    public function markSettlementPaid(Request $request, EosbSettlement $settlement): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
        ]);

        return $this->tryAction(
            fn() => $this->eosbService->markSettlementPaid($settlement, $validated['payment_date']),
            'Settlement marked as paid.'
        );
    }
}
