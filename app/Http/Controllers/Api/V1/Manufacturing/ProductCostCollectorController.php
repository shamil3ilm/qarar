<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\ProductCostCollector;
use App\Services\Manufacturing\ProductCostCollectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCostCollectorController extends Controller
{
    public function __construct(
        private readonly ProductCostCollectorService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['product_id', 'period', 'fiscal_year', 'status']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function show(int $id): JsonResponse
    {
        $collector = ProductCostCollector::with(['product:id,name,sku', 'items.costElement'])->findOrFail($id);

        return $this->success($collector);
    }

    public function postCost(Request $request, int $id): JsonResponse
    {
        $collector = ProductCostCollector::findOrFail($id);

        $validated = $request->validate([
            'cost_element_id' => ['nullable', 'integer', 'exists:cost_elements,id'],
            'cost_category'   => ['required', Rule::in(['material', 'labor', 'overhead', 'other'])],
            'standard_cost'   => ['required', 'numeric', 'min:0'],
            'actual_cost'     => ['required', 'numeric', 'min:0'],
        ]);

        $this->service->postCost($collector, $validated);

        return $this->success($collector->fresh(['items']));
    }

    public function recalculate(int $id): JsonResponse
    {
        $collector = ProductCostCollector::findOrFail($id);

        $this->service->recalculate($collector);

        return $this->success($collector->fresh());
    }

    public function close(int $id): JsonResponse
    {
        $collector = ProductCostCollector::findOrFail($id);

        $collector = $this->service->close($collector);

        return $this->success($collector, 'Cost collector closed successfully.');
    }
}
