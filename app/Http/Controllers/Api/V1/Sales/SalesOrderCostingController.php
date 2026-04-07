<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesOrderCostEstimate;
use App\Services\Sales\SalesOrderCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderCostingController extends Controller
{
    public function __construct(
        private readonly SalesOrderCostingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'sales_order_id', 'quotation_id']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'sales_order_id'     => ['nullable', 'integer', 'exists:sales_orders,id'],
            'quotation_id'       => ['nullable', 'integer', 'exists:quotations,id'],
            'costing_version_id' => ['nullable', 'integer', 'exists:costing_versions,id'],
        ]);

        $estimate = $this->service->createEstimate(array_merge($validated, [
            'organization_id' => $orgId,
            'costed_by'       => $request->user()?->id,
        ]));

        return $this->created($estimate);
    }

    public function show(int $id): JsonResponse
    {
        $estimate = SalesOrderCostEstimate::with(['items.product', 'items.costElement', 'salesOrder', 'costedBy:id,name'])->findOrFail($id);

        return $this->success($estimate);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $estimate = SalesOrderCostEstimate::findOrFail($id);

        $validated = $request->validate([
            'sales_order_id'     => ['nullable', 'integer', 'exists:sales_orders,id'],
            'quotation_id'       => ['nullable', 'integer', 'exists:quotations,id'],
            'costing_version_id' => ['nullable', 'integer', 'exists:costing_versions,id'],
        ]);

        $estimate = $this->service->update($estimate, $validated);

        return $this->success($estimate);
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $orgId    = $this->organizationId($request);
        $estimate = SalesOrderCostEstimate::findOrFail($id);

        $validated = $request->validate([
            'sales_order_line_id' => ['nullable', 'integer', 'exists:sales_order_lines,id'],
            'product_id'          => ['nullable', 'integer', 'exists:products,id'],
            'cost_element_id'     => ['nullable', 'integer', 'exists:cost_elements,id'],
            'cost_category'       => ['required', Rule::in(['material', 'labor', 'overhead', 'other'])],
            'quantity'            => ['required', 'numeric', 'min:0'],
            'cost_per_unit'       => ['required', 'numeric', 'min:0'],
            'revenue'             => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = $this->service->addItem($estimate, array_merge($validated, [
            'organization_id' => $orgId,
        ]));

        return $this->created($item);
    }

    public function release(int $id): JsonResponse
    {
        $estimate = SalesOrderCostEstimate::findOrFail($id);

        $estimate = $this->service->release($estimate);

        return $this->success($estimate, 'Cost estimate released successfully.');
    }
}
