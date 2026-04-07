<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\BomCoProduct;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrderCoProductActual;
use App\Services\Manufacturing\CoProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CoProductController extends Controller
{
    public function __construct(
        private readonly CoProductService $service
    ) {}

    public function indexForBom(int $bomId): JsonResponse
    {
        BomTemplate::findOrFail($bomId);
        $coProducts = $this->service->getForBom($bomId);

        return $this->success($coProducts, 'Co/by-products retrieved successfully.');
    }

    public function addToBom(int $bomId, Request $request): JsonResponse
    {
        $bom = BomTemplate::findOrFail($bomId);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'quantity_per_base' => 'required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'cost_allocation_percent' => 'nullable|numeric|min:0|max:100',
            'is_valuated' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $coProduct = $this->service->addCoProduct($bom, $validated);

        return $this->created($coProduct->load('product'), 'Co/by-product added to BOM successfully.');
    }

    public function updateCoProduct(int $bomId, int $id, Request $request): JsonResponse
    {
        BomTemplate::findOrFail($bomId);
        $coProduct = BomCoProduct::where('bom_template_id', $bomId)->findOrFail($id);
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'product_id' => ['sometimes', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'quantity_per_base' => 'sometimes|required|numeric|min:0.0001',
            'unit_of_measure' => 'nullable|string|max:20',
            'cost_allocation_percent' => 'nullable|numeric|min:0|max:100',
            'is_valuated' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
        ]);

        $updated = $this->service->updateCoProduct($coProduct, $validated);

        return $this->success($updated->load('product'), 'Co/by-product updated successfully.');
    }

    public function removeFromBom(int $bomId, int $id): JsonResponse
    {
        BomTemplate::findOrFail($bomId);
        $coProduct = BomCoProduct::where('bom_template_id', $bomId)->findOrFail($id);
        $this->service->removeCoProduct($coProduct);

        return $this->noContent();
    }

    public function indexForWorkOrder(int $workOrderId): JsonResponse
    {
        $actuals = $this->service->getForWorkOrder($workOrderId);

        return $this->success($actuals, 'Work order co/by-product actuals retrieved.');
    }

    public function postActuals(int $workOrderId, Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'actuals' => 'required|array|min:1',
            'actuals.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'actuals.*.bom_co_product_id' => 'nullable|exists:bom_co_products,id',
            'actuals.*.co_product_type' => 'nullable|in:co_product,by_product,scrap',
            'actuals.*.planned_quantity' => 'nullable|numeric|min:0',
            'actuals.*.actual_quantity' => 'required|numeric|min:0',
            'actuals.*.unit_of_measure' => 'nullable|string|max:20',
            'actuals.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
        ]);

        $actualsWithOrg = array_map(function (array $item) use ($orgId, $workOrderId): array {
            return array_merge($item, ['organization_id' => $orgId, 'work_order_id' => $workOrderId]);
        }, $validated['actuals']);

        $results = $this->service->postActual($workOrderId, $actualsWithOrg);

        return $this->success($results, 'Co/by-product actuals posted successfully.');
    }

    public function postToStock(int $workOrderId, int $actualId): JsonResponse
    {
        $actual = WorkOrderCoProductActual::where('work_order_id', $workOrderId)->findOrFail($actualId);
        $this->service->postToStock($actual);

        return $this->success(null, 'Co/by-product actual posted to stock.');
    }
}
