<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\PayrollCorrection;
use App\Services\HR\PayrollCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollCorrectionController extends Controller
{
    public function __construct(
        private readonly PayrollCorrectionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->list($request->only([
            'status', 'employee_id', 'original_period_id', 'per_page',
        ]));

        return $this->paginated($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'                  => 'required|integer|exists:employees,id',
            'original_payroll_period_id'   => 'required|integer|exists:payroll_periods,id',
            'correction_payroll_period_id' => 'nullable|integer|exists:payroll_periods,id',
            'correction_type'              => 'required|in:salary_change,component_adjustment,tax_correction,deduction_adjustment',
            'original_amount'              => 'required|numeric',
            'corrected_amount'             => 'required|numeric',
            'reason'                       => 'nullable|string',
        ]);

        $correction = $this->service->create($validated);

        return $this->created($correction->load(['employee', 'originalPeriod']), 'Payroll correction created.');
    }

    public function show(string $id): JsonResponse
    {
        $correction = PayrollCorrection::with(['employee', 'originalPeriod', 'correctionPeriod', 'approver'])
            ->findOrFail($id);

        return $this->success($correction);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $correction = PayrollCorrection::findOrFail($id);

        if ($correction->status !== PayrollCorrection::STATUS_DRAFT) {
            return $this->error('Only draft corrections can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'correction_payroll_period_id' => 'nullable|integer|exists:payroll_periods,id',
            'correction_type'              => 'sometimes|in:salary_change,component_adjustment,tax_correction,deduction_adjustment',
            'original_amount'              => 'sometimes|numeric',
            'corrected_amount'             => 'sometimes|numeric',
            'reason'                       => 'nullable|string',
        ]);

        if (isset($validated['original_amount']) || isset($validated['corrected_amount'])) {
            $original   = (float) ($validated['original_amount'] ?? $correction->original_amount);
            $corrected  = (float) ($validated['corrected_amount'] ?? $correction->corrected_amount);
            $validated['difference_amount'] = $corrected - $original;
        }

        $correction->update($validated);

        return $this->success($correction->fresh(['employee', 'originalPeriod']), 'Payroll correction updated.');
    }

    public function approve(string $id): JsonResponse
    {
        $correction = PayrollCorrection::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->approve($correction, auth()->id())->load(['employee', 'approver']),
            'Payroll correction approved.',
            'INVALID_STATUS'
        );
    }

    public function post(string $id): JsonResponse
    {
        $correction = PayrollCorrection::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->post($correction)->load(['employee', 'originalPeriod']),
            'Payroll correction posted.',
            'INVALID_STATUS'
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $correction = PayrollCorrection::findOrFail($id);

        return $this->tryAction(
            fn() => $this->service->cancel($correction),
            'Payroll correction cancelled.',
            'INVALID_STATUS'
        );
    }
}
