<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ValuationCategory;
use App\Services\Inventory\SplitValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SplitValuationController extends Controller
{
    public function __construct(private readonly SplitValuationService $service) {}

    /** GET /split-valuation?product_id=&warehouse_id= */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer']]);

        $splits = $this->service->getSplits(
            organizationId: $request->user()->organization_id,
            productId:      (int) $request->product_id,
            warehouseId:    $request->warehouse_id ? (int) $request->warehouse_id : null,
        );

        return $this->successResponse($splits, 'Split valuations retrieved');
    }

    /** POST /split-valuation/categories */
    public function createCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'    => ['required', 'integer'],
            'category_code' => ['required', 'string', 'max:50'],
            'category_name' => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
        ]);

        $category = $this->service->createCategory(
            organizationId: $request->user()->organization_id,
            productId:      $data['product_id'],
            categoryCode:   $data['category_code'],
            categoryName:   $data['category_name'],
            description:    $data['description'] ?? null,
        );

        return $this->successResponse($category, 'Valuation category created', 201);
    }

    /** POST /split-valuation/categories/{category}/types */
    public function createType(Request $request, ValuationCategory $category): JsonResponse
    {
        $data = $request->validate([
            'type_code'   => ['required', 'string', 'max:50'],
            'type_name'   => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $type = $this->service->createType(
            organizationId: $request->user()->organization_id,
            category:       $category,
            typeCode:       $data['type_code'],
            typeName:       $data['type_name'],
            description:    $data['description'] ?? null,
        );

        return $this->successResponse($type, 'Valuation type created', 201);
    }

    /** POST /split-valuation/goods-receipt */
    public function goodsReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'         => ['required', 'integer'],
            'valuation_type_id'  => ['required', 'integer'],
            'quantity'           => ['required', 'numeric', 'min:0.0001'],
            'unit_price'         => ['required', 'numeric', 'min:0'],
            'currency'           => ['required', 'string', 'size:3'],
            'warehouse_id'       => ['nullable', 'integer'],
        ]);

        $split = $this->service->goodsReceipt(
            organizationId:  $request->user()->organization_id,
            productId:       $data['product_id'],
            valuationTypeId: $data['valuation_type_id'],
            quantity:        (float) $data['quantity'],
            unitPrice:       (float) $data['unit_price'],
            currency:        $data['currency'],
            warehouseId:     isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
        );

        return $this->successResponse($split, 'Goods receipt posted to split valuation', 201);
    }

    /** POST /split-valuation/goods-issue */
    public function goodsIssue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'        => ['required', 'integer'],
            'valuation_type_id' => ['required', 'integer'],
            'quantity'          => ['required', 'numeric', 'min:0.0001'],
            'warehouse_id'      => ['nullable', 'integer'],
        ]);

        $split = $this->service->goodsIssue(
            organizationId:  $request->user()->organization_id,
            productId:       $data['product_id'],
            valuationTypeId: $data['valuation_type_id'],
            quantity:        (float) $data['quantity'],
            warehouseId:     isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
        );

        return $this->successResponse($split, 'Goods issue posted to split valuation');
    }

    /** POST /split-valuation/revaluate (MR22) */
    public function revaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'        => ['required', 'integer'],
            'valuation_type_id' => ['required', 'integer'],
            'new_price'         => ['required', 'numeric', 'min:0'],
            'warehouse_id'      => ['nullable', 'integer'],
        ]);

        $result = $this->service->revaluate(
            organizationId:  $request->user()->organization_id,
            productId:       $data['product_id'],
            valuationTypeId: $data['valuation_type_id'],
            newPrice:        (float) $data['new_price'],
            warehouseId:     isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
        );

        return $this->successResponse($result, 'Split valuation price revaluation applied');
    }
}
