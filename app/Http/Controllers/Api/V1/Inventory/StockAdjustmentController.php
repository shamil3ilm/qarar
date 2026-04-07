<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockAdjustmentResource;
use App\Models\Inventory\StockAdjustment;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private StockAdjustmentService $adjustmentService
    ) {
    }

    /**
     * List stock adjustments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockAdjustment::with(['warehouse', 'lines.product', 'lines.variant', 'creator', 'poster'])
            ->latest()
            ->when($request->has('warehouse_id'), fn($q) => $q->inWarehouse($request->integer('warehouse_id')))
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('reason'), fn($q) => $q->byReason($request->input('reason')))
            ->when($request->has('from_date'), fn($q) => $q->where('adjustment_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('adjustment_date', '<=', $request->input('to_date')));

        $adjustments = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($adjustments, StockAdjustmentResource::class);
    }

    /**
     * Create a new stock adjustment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'adjustment_date' => 'required|date',
            'reason' => 'required|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.location_id' => 'nullable|integer|exists:warehouse_locations,id',
            'lines.*.actual_quantity' => 'required|numeric|min:0',
            'lines.*.system_quantity' => 'nullable|numeric',
            'lines.*.unit_cost' => 'nullable|numeric',
            'lines.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $adjustment = $this->adjustmentService->create(
                collect($validated)->except('lines')->toArray(),
                $validated['lines']
            );

            return $this->created(new StockAdjustmentResource($adjustment), 'Stock adjustment created successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a stock adjustment.
     */
    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $stockAdjustment->load(['warehouse', 'lines.product', 'lines.variant', 'creator', 'poster']);

        return $this->success(new StockAdjustmentResource($stockAdjustment));
    }

    /**
     * Update a draft stock adjustment.
     */
    public function update(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->isEditable()) {
            return $this->error(
                'Only draft adjustments can be updated.',
                'INVALID_STATUS',
                422
            );
        }

        $validated = $request->validate([
            'adjustment_date' => 'sometimes|date',
            'reason' => 'sometimes|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', auth()->user()->organization_id)],
            'lines.*.location_id' => 'nullable|integer|exists:warehouse_locations,id',
            'lines.*.actual_quantity' => 'required|numeric|min:0',
            'lines.*.system_quantity' => 'nullable|numeric',
            'lines.*.unit_cost' => 'nullable|numeric',
            'lines.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            $adjustment = $this->adjustmentService->update(
                $stockAdjustment,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Post a stock adjustment.
     */
    public function post(StockAdjustment $stockAdjustment): JsonResponse
    {
        if (!$stockAdjustment->canPost()) {
            return $this->error(
                'Adjustment cannot be posted. Only draft adjustments with lines can be posted.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $adjustment = $this->adjustmentService->post($stockAdjustment, auth()->id());

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment posted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Cancel a draft stock adjustment.
     */
    public function cancel(StockAdjustment $stockAdjustment): JsonResponse
    {
        if ($stockAdjustment->status !== StockAdjustment::STATUS_DRAFT) {
            return $this->error(
                'Only draft adjustments can be cancelled.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $adjustment = $this->adjustmentService->cancel($stockAdjustment);

            return $this->success(new StockAdjustmentResource($adjustment), 'Stock adjustment cancelled.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Get adjustment summary.
     */
    public function summary(StockAdjustment $stockAdjustment): JsonResponse
    {
        $summary = $this->adjustmentService->getSummary($stockAdjustment);

        return $this->success($summary);
    }

    /**
     * Quick adjustment for a single product.
     */
    public function quickAdjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', auth()->user()->organization_id)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'actual_quantity' => 'required|numeric|min:0',
            'reason' => 'required|in:damage,theft,expiry,count_correction,opening_balance,other',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $adjustment = $this->adjustmentService->quickAdjust(
                $validated['product_id'],
                $validated['warehouse_id'],
                $validated['actual_quantity'],
                $validated['reason'],
                auth()->id(),
                $validated['notes'] ?? null
            );

            // Auto-post quick adjustments
            $this->adjustmentService->post($adjustment, auth()->id());

            return $this->success(new StockAdjustmentResource($adjustment->fresh()), 'Stock adjusted successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }
}
