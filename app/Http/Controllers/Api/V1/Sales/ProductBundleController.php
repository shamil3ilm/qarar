<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\ProductBundle;
use App\Services\Sales\OffersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBundleController extends Controller
{
    public function __construct(private OffersService $offersService) {}

    public function index(Request $request): JsonResponse
    {
        $bundles = ProductBundle::where('organization_id', auth()->user()->organization_id)
            ->with('items.product')
            ->orderBy('display_order')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($bundles);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:product_bundles,sku,NULL,id,organization_id,' . auth()->user()->organization_id,
            'pricing_type' => 'required|string|in:fixed,percentage_discount,custom,discount,calculated',
            'bundle_price' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $bundle = $this->offersService->createBundle($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($bundle);
    }

    public function show(ProductBundle $bundle): JsonResponse
    {
        return $this->success($bundle->load('items.product'));
    }

    public function update(Request $request, ProductBundle $bundle): JsonResponse
    {
        $bundle->update($request->all());
        return $this->success($bundle->fresh()->load('items'));
    }

    public function destroy(ProductBundle $bundle): JsonResponse
    {
        $bundle->delete();
        return $this->success(['message' => 'Bundle deleted']);
    }

    public function calculatePrice(Request $request, ProductBundle $bundle): JsonResponse
    {
        $result = $this->offersService->calculateBundlePrice($bundle->id, $request->input('selected_items', []));
        return $this->success($result);
    }
}
