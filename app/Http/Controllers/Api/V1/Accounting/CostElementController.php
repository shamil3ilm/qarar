<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CostElement;
use App\Services\Accounting\CostElementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostElementController extends Controller
{
    public function __construct(
        private readonly CostElementService $service
    ) {}

    /**
     * List cost elements with optional filters.
     *
     * GET /cost-elements
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'element_type', 'is_active', 'cost_element_category', 'per_page']);

        return $this->paginated($this->service->index($filters));
    }

    /**
     * Create a new cost element.
     *
     * POST /cost-elements
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'code'                  => ['required', 'string', 'max:20'],
            'name'                  => ['required', 'string', 'max:150'],
            'element_type'          => ['required', Rule::in([CostElement::TYPE_PRIMARY, CostElement::TYPE_SECONDARY])],
            'gl_account_id'         => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cost_element_category' => ['nullable', Rule::in([
                CostElement::CATEGORY_GENERAL,
                CostElement::CATEGORY_DEPRECIATION,
                CostElement::CATEGORY_IMPUTED,
                CostElement::CATEGORY_REVENUE,
                CostElement::CATEGORY_INTERNAL_SETTLEMENT,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $costElement = $this->service->store(array_merge($validated, ['organization_id' => $orgId]));

        return $this->created($costElement->load('glAccount:id,code,name'));
    }

    /**
     * Show a single cost element.
     *
     * GET /cost-elements/{costElement}
     */
    public function show(CostElement $costElement): JsonResponse
    {
        return $this->success(
            $costElement->load(['glAccount:id,code,name', 'activityTypes:id,code,name'])
        );
    }

    /**
     * Update a cost element.
     *
     * PUT /cost-elements/{costElement}
     */
    public function update(Request $request, CostElement $costElement): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => ['sometimes', 'required', 'string', 'max:20'],
            'name'                  => ['sometimes', 'required', 'string', 'max:150'],
            'element_type'          => ['sometimes', 'required', Rule::in([CostElement::TYPE_PRIMARY, CostElement::TYPE_SECONDARY])],
            'gl_account_id'         => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cost_element_category' => ['nullable', Rule::in([
                CostElement::CATEGORY_GENERAL,
                CostElement::CATEGORY_DEPRECIATION,
                CostElement::CATEGORY_IMPUTED,
                CostElement::CATEGORY_REVENUE,
                CostElement::CATEGORY_INTERNAL_SETTLEMENT,
            ])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updated = $this->service->update($costElement, $validated);

        return $this->success($updated);
    }

    /**
     * Soft-delete a cost element.
     *
     * DELETE /cost-elements/{costElement}
     */
    public function destroy(CostElement $costElement): JsonResponse
    {
        $this->service->destroy($costElement);

        return $this->success(['message' => 'Cost element deleted.']);
    }
}
