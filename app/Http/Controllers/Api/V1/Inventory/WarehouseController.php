<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    /**
     * List warehouses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with(['branch', 'manager'])
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->has('branch_id'), fn($q) => $q->where('branch_id', $request->input('branch_id')));

        $warehouses = $query->get();

        return $this->success(WarehouseResource::collection($warehouses));
    }

    /**
     * Create a new warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:warehouses,code',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'manager_id' => 'nullable|integer|exists:users,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            Warehouse::where('is_default', true)->each(fn (Warehouse $w) => $w->update(['is_default' => false]));
        }

        $warehouse = Warehouse::create($validated);

        return $this->created(new WarehouseResource($warehouse), 'Warehouse created successfully.');
    }

    /**
     * Show a warehouse.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load(['branch', 'manager', 'locations']);

        return $this->success(new WarehouseResource($warehouse));
    }

    /**
     * Update a warehouse.
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:20|unique:warehouses,code,' . $warehouse->id,
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'manager_id' => 'nullable|integer|exists:users,id',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'allow_negative_stock' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if (($validated['is_default'] ?? false) && !$warehouse->is_default) {
            Warehouse::where('is_default', true)->each(fn (Warehouse $w) => $w->update(['is_default' => false]));
        }

        $warehouse->update($validated);

        return $this->success(new WarehouseResource($warehouse->fresh()), 'Warehouse updated successfully.');
    }

    /**
     * Delete a warehouse.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        // Check for stock
        if ($warehouse->stockLevels()->where('quantity', '>', 0)->exists()) {
            return $this->error('Cannot delete warehouse with existing stock.', 'VALIDATION_ERROR', 422);
        }

        $warehouse->delete();

        return $this->success(null, 'Warehouse deleted successfully.');
    }

    /**
     * Get stock valuation for a warehouse.
     */
    public function stockValuation(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $result    = $this->stockService->getStockValuation(
                $warehouse->id,
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
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Get low stock items in a warehouse.
     */
    public function lowStock(Request $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $items = $this->stockService->getLowStockProducts(
                $warehouse->id,
                $request->integer('per_page', 25),
            );

            return $this->paginated($items);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Set warehouse as default.
     */
    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        Warehouse::where('is_default', true)
            ->each(fn (Warehouse $w) => $w->update(['is_default' => false]));
        $warehouse->update(['is_default' => true]);

        return $this->success(new WarehouseResource($warehouse->fresh()), 'Default warehouse updated.');
    }
}
