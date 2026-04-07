<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CostSplittingRule;
use App\Services\Accounting\CostSplittingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostSplittingController extends Controller
{
    public function __construct(
        private readonly CostSplittingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'cost_center_id']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'cost_center_id'      => ['required', 'integer', 'exists:cost_centers,id'],
            'cost_element_id'     => ['nullable', 'integer', 'exists:cost_elements,id'],
            'fixed_percentage'    => ['required', 'numeric', 'min:0', 'max:100'],
            'variable_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'splitting_basis'     => ['nullable', Rule::in([CostSplittingRule::BASIS_ACTIVITY_QUANTITY, CostSplittingRule::BASIS_CAPACITY_UTILIZATION, CostSplittingRule::BASIS_MANUAL])],
            'is_active'           => ['nullable', 'boolean'],
            'valid_from'          => ['required', 'date'],
            'valid_to'            => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $rule = $this->service->create(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($rule);
    }

    public function show(int $id): JsonResponse
    {
        $rule = CostSplittingRule::with(['costCenter', 'costElement'])->findOrFail($id);

        return $this->success($rule);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = CostSplittingRule::findOrFail($id);

        $validated = $request->validate([
            'cost_element_id'     => ['nullable', 'integer', 'exists:cost_elements,id'],
            'fixed_percentage'    => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'variable_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'splitting_basis'     => ['sometimes', Rule::in([CostSplittingRule::BASIS_ACTIVITY_QUANTITY, CostSplittingRule::BASIS_CAPACITY_UTILIZATION, CostSplittingRule::BASIS_MANUAL])],
            'is_active'           => ['sometimes', 'boolean'],
            'valid_from'          => ['sometimes', 'date'],
            'valid_to'            => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $rule = $this->service->update($rule, $validated);

        return $this->success($rule);
    }

    public function destroy(int $id): JsonResponse
    {
        $rule = CostSplittingRule::findOrFail($id);
        $rule->delete();

        return $this->noContent();
    }

    public function runSplitting(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $result = $this->service->runSplitting(
            (int) $validated['period'],
            (int) $validated['fiscal_year'],
            (int) $orgId
        );

        return $this->success($result, 'Cost splitting run completed.');
    }

    public function results(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $results = $this->service->getResults(
            (int) $validated['period'],
            (int) $validated['fiscal_year']
        );

        return $this->success($results);
    }
}
