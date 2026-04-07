<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ShelfLabel;
use App\Services\Inventory\ShelfLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShelfLabelController extends Controller
{
    public function __construct(
        private ShelfLabelService $shelfLabelService
    ) {}

    /**
     * List shelf labels.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ShelfLabel::with(['product', 'variant', 'branch'])
            ->latest()
            ->when($request->has('branch_id'), fn($q) => $q->byBranch($request->integer('branch_id')))
            ->when($request->has('product_id'), fn($q) => $q->forProduct($request->integer('product_id')))
            ->when($request->has('label_type'), fn($q) => $q->byLabelType($request->input('label_type')))
            ->when($request->has('aisle'), fn($q) => $q->inAisle($request->input('aisle')))
            ->when($request->boolean('needs_reprint'), fn($q) => $q->needsReprint())
            ->when($request->has('is_digital'), fn($q) => $request->boolean('is_digital') ? $q->digital() : $q->where('is_digital', false))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')));

        $labels = $query->get();

        return $this->success($labels);
    }

    /**
     * Create a shelf label.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'product_name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode_value' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'unit_label' => 'nullable|string|max:50',
            'price_per_unit' => 'nullable|numeric|min:0',
            'unit_measure_label' => 'nullable|string|max:50',
            'aisle' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'is_digital' => 'boolean',
            'esl_device_id' => 'nullable|string|max:255',
        ]);

        $label = $this->shelfLabelService->create($validated);

        return $this->created($label, 'Shelf label created successfully.');
    }

    /**
     * Show a shelf label.
     */
    public function show(ShelfLabel $shelfLabel): JsonResponse
    {
        $shelfLabel->load(['product', 'variant', 'branch']);

        return $this->success($shelfLabel);
    }

    /**
     * Update a shelf label.
     */
    public function update(Request $request, ShelfLabel $shelfLabel): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode_value' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'unit_label' => 'nullable|string|max:50',
            'price_per_unit' => 'nullable|numeric|min:0',
            'unit_measure_label' => 'nullable|string|max:50',
            'aisle' => 'nullable|string|max:50',
            'shelf' => 'nullable|string|max:50',
            'position' => 'nullable|string|max:50',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'is_active' => 'boolean',
        ]);

        // Mark for reprint if price changed
        if (isset($validated['price']) && $validated['price'] != $shelfLabel->price) {
            $validated['needs_reprint'] = true;
        }

        $shelfLabel->update($validated);

        return $this->success($shelfLabel->fresh(), 'Shelf label updated successfully.');
    }

    /**
     * Delete a shelf label.
     */
    public function destroy(ShelfLabel $shelfLabel): JsonResponse
    {
        $shelfLabel->delete();

        return $this->success(null, 'Shelf label deleted successfully.');
    }

    /**
     * Generate shelf labels for given products.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'integer|exists:products,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'currency_code' => 'nullable|string|size:3',
            'label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
        ]);

        $branchId = $validated['branch_id']
            ?? (int) $request->header('X-Branch-Id')
            ?: auth()->user()->branches()->wherePivot('is_default', true)->first()?->id;

        $currencyCode = $validated['currency_code']
            ?? auth()->user()->organization->base_currency
            ?? 'SAR';

        $items = [];
        foreach ($validated['product_ids'] as $productId) {
            $items[] = [
                'product_id' => $productId,
                'label_type' => $validated['label_type'] ?? 'standard',
                'label_size' => $validated['label_size'] ?? 'standard',
            ];
        }

        $results = $this->shelfLabelService->bulkCreate($items, $branchId, $currencyCode);

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ], "Generated {$successCount} shelf labels.");
    }

    /**
     * Bulk create shelf labels.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'currency_code' => 'required|string|size:3',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.compare_at_price' => 'nullable|numeric|min:0',
            'items.*.label_type' => 'nullable|string|in:standard,promotional,clearance,new_arrival,organic,halal',
            'items.*.label_size' => 'nullable|string|in:small,standard,large,shelf_strip',
            'items.*.aisle' => 'nullable|string|max:50',
            'items.*.shelf' => 'nullable|string|max:50',
            'items.*.position' => 'nullable|string|max:50',
        ]);

        $results = $this->shelfLabelService->bulkCreate(
            $validated['items'],
            $validated['branch_id'],
            $validated['currency_code']
        );

        $successCount = collect($results)->where('success', true)->count();
        $failedCount = collect($results)->where('success', false)->count();

        return $this->success([
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'failed' => $failedCount,
            ],
        ], "Bulk creation completed: {$successCount} succeeded, {$failedCount} failed.");
    }

    /**
     * Mark labels for reprint.
     */
    public function reprint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label_ids' => 'required|array|min:1|max:100',
            'label_ids.*' => 'integer|exists:shelf_labels,id',
        ]);

        $count = $this->shelfLabelService->markForReprint($validated['label_ids']);

        return $this->success([
            'marked_count' => $count,
        ], "{$count} labels marked for reprint.");
    }
}
