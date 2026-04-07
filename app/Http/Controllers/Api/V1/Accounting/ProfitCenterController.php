<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ProfitCenter;
use App\Services\Accounting\ProfitCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfitCenterController extends Controller
{
    public function __construct(
        private readonly ProfitCenterService $service
    ) {}

    // ================================================================
    // CRUD
    // ================================================================

    /**
     * List profit centers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProfitCenter::with(['parent:id,code,name', 'manager:id,first_name,last_name'])
            ->orderBy('code')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->search;
                $q->where(function ($q) use ($search): void {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->filled('parent_id'),
                fn($q) => $q->where('parent_id', $request->integer('parent_id')),
                fn($q) => $q->when($request->boolean('roots_only'), fn($q) => $q->whereNull('parent_id'))
            );

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    /**
     * Create a new profit center.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50'],
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'parent_id'      => ['nullable', 'integer', 'exists:profit_centers,id'],
            'manager_id'     => ['nullable', 'integer', 'exists:employees,id'],
            'status'         => ['nullable', Rule::in([ProfitCenter::STATUS_ACTIVE, ProfitCenter::STATUS_INACTIVE])],
            'valid_from'     => ['nullable', 'date'],
            'valid_to'       => ['nullable', 'date', 'after_or_equal:valid_from'],
            'gl_account_id'  => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $profitCenter = $this->service->createProfitCenter(
            array_merge($validated, ['organization_id' => $orgId]),
            $request->user()->id
        );

        return $this->created($profitCenter->load(['parent:id,code,name', 'manager:id,first_name,last_name']));
    }

    /**
     * Show a single profit center.
     */
    public function show(ProfitCenter $profitCenter): JsonResponse
    {
        $profitCenter->load([
            'parent:id,code,name',
            'children:id,code,name,status',
            'manager:id,first_name,last_name',
            'glAccount:id,code,name',
        ]);

        return $this->success($profitCenter);
    }

    /**
     * Update a profit center.
     */
    public function update(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $validated = $request->validate([
            'code'           => ['sometimes', 'required', 'string', 'max:50'],
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'parent_id'      => ['nullable', 'integer', 'exists:profit_centers,id'],
            'manager_id'     => ['nullable', 'integer', 'exists:employees,id'],
            'status'         => ['nullable', Rule::in([ProfitCenter::STATUS_ACTIVE, ProfitCenter::STATUS_INACTIVE])],
            'valid_from'     => ['nullable', 'date'],
            'valid_to'       => ['nullable', 'date', 'after_or_equal:valid_from'],
            'gl_account_id'  => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        $updated = $this->service->updateProfitCenter($profitCenter, $validated, $request->user()->id);

        return $this->success($updated->load(['parent:id,code,name', 'manager:id,first_name,last_name']));
    }

    /**
     * Soft-delete a profit center.
     */
    public function destroy(ProfitCenter $profitCenter): JsonResponse
    {
        $profitCenter->delete();

        return $this->success(['message' => 'Profit center deleted.']);
    }

    /**
     * Deactivate without deleting.
     */
    public function deactivate(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $updated = $this->service->deactivate($profitCenter, $request->user()->id);

        return $this->success($updated);
    }

    // ================================================================
    // Report
    // ================================================================

    /**
     * Profit center P&L report for a date range.
     *
     * GET /controlling/profit-centers/{profitCenter}/report?from=&to=
     */
    public function report(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $this->organizationId($request);

        $data = $this->service->getProfitCenterReport(
            $orgId,
            $request->from,
            $request->to,
            $profitCenter->id
        );

        $summary = $data[0] ?? [
            'profit_center_id' => $profitCenter->id,
            'code'             => $profitCenter->code,
            'name'             => $profitCenter->name,
            'revenue'          => 0.0,
            'expense'          => 0.0,
            'profit'           => 0.0,
        ];

        return $this->success([
            'profit_center' => $profitCenter->only(['id', 'code', 'name']),
            'period'        => ['from' => $request->from, 'to' => $request->to],
            'summary'       => $summary,
        ]);
    }

    /**
     * Aggregated report across ALL profit centers.
     *
     * GET /controlling/profit-centers/report?from=&to=
     */
    public function reportAll(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $orgId = $this->organizationId($request);

        $data = $this->service->getProfitCenterReport($orgId, $request->from, $request->to);

        return $this->success([
            'period' => ['from' => $request->from, 'to' => $request->to],
            'lines'  => $data,
        ]);
    }

    // ================================================================
    // Period Planning
    // ================================================================

    /**
     * Upsert a period plan for a profit center.
     *
     * POST /controlling/profit-centers/{profitCenter}/plan
     */
    public function setPlan(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'period'       => ['required', 'integer', 'min:1', 'max:12'],
            'plan_revenue' => ['required', 'numeric', 'min:0'],
            'plan_cost'    => ['required', 'numeric', 'min:0'],
        ]);

        return $this->tryAction(
            fn() => $this->service->setPlan(
                $profitCenter,
                (int) $validated['fiscal_year'],
                (int) $validated['period'],
                (float) $validated['plan_revenue'],
                (float) $validated['plan_cost']
            ),
            'Success',
        );
    }

    /**
     * Get the full 12-period plan for a profit center.
     *
     * GET /controlling/profit-centers/{profitCenter}/plan?fiscal_year=2025
     */
    public function getPlan(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $plan = $this->service->getPlan($profitCenter, (int) $request->fiscal_year);

        return $this->success($plan);
    }

    /**
     * Plan vs actual P&L comparison across 12 periods.
     *
     * GET /controlling/profit-centers/{profitCenter}/plan-vs-actual?fiscal_year=2025
     */
    public function planVsActual(Request $request, ProfitCenter $profitCenter): JsonResponse
    {
        $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $report = $this->service->getPlanVsActual($profitCenter, (int) $request->fiscal_year);

        return $this->success($report);
    }
}
