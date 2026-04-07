<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\QuotaArrangementItemResource;
use App\Http\Resources\Purchase\QuotaArrangementResource;
use App\Models\Purchase\QuotaArrangement;
use App\Models\Purchase\QuotaArrangementItem;
use App\Services\Purchase\QuotaArrangementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaArrangementController extends Controller
{
    public function __construct(
        private readonly QuotaArrangementService $service
    ) {}

    /**
     * List quota arrangements.
     * Filters: product_id, warehouse_id, is_active
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['product_id', 'warehouse_id', 'is_active']);
        $perPage = $request->integer('per_page', 20);

        $arrangements = $this->service->list($filters, $perPage);

        return $this->paginated($arrangements, QuotaArrangementResource::class);
    }

    /**
     * Create a quota arrangement (optionally with items).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'valid_from'   => 'required|date',
            'valid_to'     => 'nullable|date|after_or_equal:valid_from',
            'is_active'    => 'sometimes|boolean',
            'notes'        => 'nullable|string',

            'items'                              => 'sometimes|array|min:1',
            'items.*.vendor_id'                  => 'required|exists:contacts,id',
            'items.*.purchasing_info_record_id'  => 'nullable|exists:purchasing_info_records,id',
            'items.*.quota_percentage'           => 'required|numeric|min:0.01|max:100',
            'items.*.min_lot_size'               => 'nullable|numeric|min:0',
            'items.*.max_lot_size'               => 'nullable|numeric|min:0',
            'items.*.is_blocked'                 => 'sometimes|boolean',
        ]);

        try {
            $arrangement = $this->service->create($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new QuotaArrangementResource($arrangement));
    }

    /**
     * Show a single quota arrangement with its items.
     */
    public function show(string $id): JsonResponse
    {
        $arrangement = QuotaArrangement::with(['product', 'warehouse', 'items.vendor'])
            ->findOrFail($id);

        return $this->success(new QuotaArrangementResource($arrangement));
    }

    /**
     * Update a quota arrangement header.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $arrangement = QuotaArrangement::findOrFail($id);

        $validated = $request->validate([
            'product_id'   => 'sometimes|exists:products,id',
            'warehouse_id' => 'sometimes|nullable|exists:warehouses,id',
            'valid_from'   => 'sometimes|date',
            'valid_to'     => 'sometimes|nullable|date|after_or_equal:valid_from',
            'is_active'    => 'sometimes|boolean',
            'notes'        => 'sometimes|nullable|string',
        ]);

        $arrangement = $this->service->update($arrangement, $validated);
        $arrangement->load(['product', 'warehouse', 'items.vendor']);

        return $this->success(new QuotaArrangementResource($arrangement));
    }

    /**
     * Soft-delete a quota arrangement.
     */
    public function destroy(string $id): JsonResponse
    {
        QuotaArrangement::findOrFail($id)->delete();

        return $this->noContent();
    }

    /**
     * Add an item to an existing quota arrangement.
     */
    public function addItem(Request $request, string $id): JsonResponse
    {
        $arrangement = QuotaArrangement::findOrFail($id);

        $validated = $request->validate([
            'vendor_id'                 => 'required|exists:contacts,id',
            'purchasing_info_record_id' => 'nullable|exists:purchasing_info_records,id',
            'quota_percentage'          => 'required|numeric|min:0.01|max:100',
            'min_lot_size'              => 'nullable|numeric|min:0',
            'max_lot_size'              => 'nullable|numeric|min:0',
            'is_blocked'                => 'sometimes|boolean',
        ]);

        try {
            $item = $this->service->addItem($arrangement, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new QuotaArrangementItemResource($item->load('vendor')));
    }

    /**
     * Update an item in a quota arrangement.
     */
    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $item = QuotaArrangementItem::where('quota_arrangement_id', $id)
            ->findOrFail($itemId);

        $validated = $request->validate([
            'vendor_id'                 => 'sometimes|exists:contacts,id',
            'purchasing_info_record_id' => 'sometimes|nullable|exists:purchasing_info_records,id',
            'quota_percentage'          => 'sometimes|numeric|min:0.01|max:100',
            'min_lot_size'              => 'sometimes|nullable|numeric|min:0',
            'max_lot_size'              => 'sometimes|nullable|numeric|min:0',
            'is_blocked'                => 'sometimes|boolean',
        ]);

        try {
            $item = $this->service->updateItem($item, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new QuotaArrangementItemResource($item->load('vendor')));
    }

    /**
     * Remove an item from a quota arrangement.
     */
    public function removeItem(string $id, string $itemId): JsonResponse
    {
        $item = QuotaArrangementItem::where('quota_arrangement_id', $id)
            ->findOrFail($itemId);

        try {
            $this->service->removeItem($item);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->noContent();
    }

    /**
     * Determine the best vendor source for a product and quantity.
     * POST body: { product_id, quantity, warehouse_id? }
     */
    public function determineSource(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => 'required|integer',
            'quantity'     => 'required|numeric|min:0.0001',
            'warehouse_id' => 'nullable|integer',
        ]);

        $item = $this->service->determineSource(
            (int) $validated['product_id'],
            (float) $validated['quantity'],
            isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null
        );

        if ($item === null) {
            return $this->success(null, 'No active quota arrangement found for the given product.');
        }

        return $this->success(new QuotaArrangementItemResource($item->load('vendor')));
    }

    /**
     * Reset all allocated quantities for an arrangement back to zero.
     */
    public function resetAllocations(string $id): JsonResponse
    {
        $arrangement = QuotaArrangement::findOrFail($id);
        $this->service->resetAllocations($arrangement);

        return $this->success(null, 'Allocations reset successfully.');
    }
}
