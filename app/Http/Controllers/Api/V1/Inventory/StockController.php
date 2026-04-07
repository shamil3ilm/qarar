<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Services\Inventory\ReorderPointService;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private ReorderPointService $reorderPointService
    ) {}

    /**
     * Get stock levels with filters.
     */
    public function levels(Request $request): JsonResponse
    {
        $query = StockLevel::with(['product', 'variant', 'warehouse', 'location'])
            ->when($request->has('product_id'), fn($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->has('warehouse_id'), fn($q) => $q->inWarehouse($request->integer('warehouse_id')))
            ->when($request->boolean('low_stock_only'), fn($q) => $q->lowStock())
            ->when($request->boolean('in_stock_only'), fn($q) => $q->hasStock());

        $levels = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($levels);
    }

    /**
     * Get stock movements history.
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'variant', 'warehouse', 'creator'])
            ->latest()
            ->when($request->has('product_id'), fn($q) => $q->forProduct($request->integer('product_id')))
            ->when($request->has('warehouse_id'), fn($q) => $q->inWarehouse($request->integer('warehouse_id')))
            ->when($request->has('movement_type'), fn($q) => $q->byType($request->input('movement_type')))
            ->when($request->has('direction'), fn($q) => $request->input('direction') === 'in' ? $q->incoming() : $q->outgoing())
            ->when($request->has('from_date'), fn($q) => $q->where('created_at', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('created_at', '<=', $request->input('to_date')));

        $movements = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($movements);
    }

    /**
     * Get stock valuation report.
     */
    public function valuation(Request $request): JsonResponse
    {
        $result    = $this->stockService->getStockValuation(
            $request->input('warehouse_id'),
            $request->integer('per_page', 25),
        );
        $paginator = $result['items'];

        return $this->success([
            'items'  => $paginator->items(),
            'totals' => $result['totals'],
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Get low stock report.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $items = $this->stockService->getLowStockProducts(
            $request->input('warehouse_id'),
            $request->integer('per_page', 25),
        );

        return $this->paginated($items);
    }

    /**
     * Check stock availability.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        // Support both flat format (product_id, quantity, warehouse_id) and items array format
        if ($request->has('items')) {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
                'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
                'items.*.warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
                'items.*.quantity' => 'required|numeric|gt:0',
            ]);
            $items = $validated['items'];
        } else {
            $validated = $request->validate([
                'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
                'variant_id' => 'nullable|integer|exists:product_variants,id',
                'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', $orgId)],
                'quantity' => 'required|numeric|gt:0',
            ]);
            $items = [$validated];
        }

        $results = [];
        $allAvailable = true;

        foreach ($items as $item) {
            $warehouseId = $item['warehouse_id'] ?? null;

            if ($warehouseId) {
                $available = $this->stockService->hasAvailableStock(
                    $item['product_id'],
                    $warehouseId,
                    $item['quantity'],
                    $item['variant_id'] ?? null
                );

                $stockLevel = $this->stockService->getStockLevel(
                    $item['product_id'],
                    $warehouseId,
                    $item['variant_id'] ?? null
                );

                $availableQty = $stockLevel?->getAvailableQuantity() ?? 0;
            } else {
                $availableQty = $this->stockService->getAvailableStock(
                    $item['product_id'],
                    $item['variant_id'] ?? null
                );
                $available = $availableQty >= $item['quantity'];
            }

            $results[] = [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'requested' => $item['quantity'],
                'available' => $availableQty,
                'is_available' => $available,
            ];

            if (!$available) {
                $allAvailable = false;
            }
        }

        return $this->success([
            'all_available' => $allAvailable,
            'items' => $results,
        ]);
    }

    /**
     * Reserve stock.
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        try {
            $reserved = $this->stockService->reserve(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['variant_id'] ?? null
            );

            if (!$reserved) {
                return $this->error('Insufficient stock available for reservation.', 'INSUFFICIENT_STOCK', 422);
            }

            return $this->success(null, 'Stock reserved successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Release reserved stock.
     */
    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'quantity' => 'required|numeric|gt:0',
        ]);

        try {
            $this->stockService->release(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['quantity'],
                $validated['variant_id'] ?? null
            );

            return $this->success(null, 'Stock reservation released.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Return all products at or below their reorder level.
     */
    public function reorderPoints(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        if ($request->has('product_id')) {
            $result = $this->reorderPointService->checkProduct(
                $request->integer('product_id'),
                $orgId
            );
        } else {
            $result = $this->reorderPointService->getProductsBelowReorderPoint($orgId);
        }

        return $this->success($result);
    }
}
