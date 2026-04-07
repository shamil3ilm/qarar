<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PurchasingInfoRecordResource;
use App\Models\Purchase\PurchasingInfoRecord;
use App\Models\Purchase\PurchasingInfoRecordCondition;
use App\Services\Purchase\PurchasingInfoRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchasingInfoRecordController extends Controller
{
    public function __construct(
        private readonly PurchasingInfoRecordService $service
    ) {}

    /**
     * List purchasing info records with optional filters.
     * Filters: vendor_id, product_id, info_category, is_active
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['vendor_id', 'product_id', 'info_category', 'is_active']);
        $perPage = $request->integer('per_page', 20);

        $records = $this->service->list($filters, $perPage);

        return $this->paginated($records, PurchasingInfoRecordResource::class);
    }

    /**
     * Create a new purchasing info record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'                  => 'nullable|exists:contacts,id',
            'product_id'                 => 'nullable|exists:products,id',
            'warehouse_id'               => 'nullable|exists:warehouses,id',
            'info_category'              => 'sometimes|in:standard,subcontracting,consignment,pipeline',
            'is_active'                  => 'sometimes|boolean',
            'planned_delivery_days'      => 'nullable|integer|min:0|max:65535',
            'reminder_days'              => 'nullable|integer|min:0|max:65535',
            'overdelivery_tolerance'     => 'nullable|numeric|min:0|max:999.99',
            'underdelivery_tolerance'    => 'nullable|numeric|min:0|max:999.99',
            'is_underdelivery_tolerated' => 'sometimes|boolean',
            'net_price'                  => 'nullable|numeric|min:0',
            'price_unit'                 => 'sometimes|integer|min:1',
            'currency_code'              => 'sometimes|string|size:3',
            'minimum_order_quantity'     => 'nullable|numeric|min:0',
            'standard_order_quantity'    => 'nullable|numeric|min:0',
            'last_purchase_date'         => 'nullable|date',
            'last_purchase_price'        => 'nullable|numeric|min:0',
            'notes'                      => 'nullable|string',
        ]);

        $record = $this->service->create($validated);
        $record->load(['vendor', 'product', 'warehouse', 'conditions']);

        return $this->created(new PurchasingInfoRecordResource($record));
    }

    /**
     * Show a single purchasing info record.
     */
    public function show(string $id): JsonResponse
    {
        $record = PurchasingInfoRecord::with(['vendor', 'product', 'warehouse', 'conditions'])
            ->findOrFail($id);

        return $this->success(new PurchasingInfoRecordResource($record));
    }

    /**
     * Update a purchasing info record.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $record = PurchasingInfoRecord::findOrFail($id);

        $validated = $request->validate([
            'vendor_id'                  => 'sometimes|nullable|exists:contacts,id',
            'product_id'                 => 'sometimes|nullable|exists:products,id',
            'warehouse_id'               => 'sometimes|nullable|exists:warehouses,id',
            'info_category'              => 'sometimes|in:standard,subcontracting,consignment,pipeline',
            'is_active'                  => 'sometimes|boolean',
            'planned_delivery_days'      => 'sometimes|nullable|integer|min:0|max:65535',
            'reminder_days'              => 'sometimes|nullable|integer|min:0|max:65535',
            'overdelivery_tolerance'     => 'sometimes|nullable|numeric|min:0|max:999.99',
            'underdelivery_tolerance'    => 'sometimes|nullable|numeric|min:0|max:999.99',
            'is_underdelivery_tolerated' => 'sometimes|boolean',
            'net_price'                  => 'sometimes|nullable|numeric|min:0',
            'price_unit'                 => 'sometimes|integer|min:1',
            'currency_code'              => 'sometimes|string|size:3',
            'minimum_order_quantity'     => 'sometimes|nullable|numeric|min:0',
            'standard_order_quantity'    => 'sometimes|nullable|numeric|min:0',
            'last_purchase_date'         => 'sometimes|nullable|date',
            'last_purchase_price'        => 'sometimes|nullable|numeric|min:0',
            'notes'                      => 'sometimes|nullable|string',
        ]);

        $record = $this->service->update($record, $validated);
        $record->load(['vendor', 'product', 'warehouse', 'conditions']);

        return $this->success(new PurchasingInfoRecordResource($record));
    }

    /**
     * Soft-delete a purchasing info record.
     */
    public function destroy(string $id): JsonResponse
    {
        $record = PurchasingInfoRecord::findOrFail($id);
        $record->delete();

        return $this->noContent();
    }

    /**
     * Add a time-banded pricing condition.
     */
    public function addCondition(Request $request, string $id): JsonResponse
    {
        $record = PurchasingInfoRecord::findOrFail($id);

        $validated = $request->validate([
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'net_price'        => 'required|numeric|min:0',
            'price_unit'       => 'sometimes|integer|min:1',
            'currency_code'    => 'sometimes|string|size:3',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active'        => 'sometimes|boolean',
        ]);

        $condition = $this->service->addCondition($record, $validated);

        return $this->created(
            new \App\Http\Resources\Purchase\PurchasingInfoRecordConditionResource($condition)
        );
    }

    /**
     * Update an existing pricing condition.
     */
    public function updateCondition(Request $request, string $id, string $conditionId): JsonResponse
    {
        // Verify the condition belongs to the specified record.
        $condition = PurchasingInfoRecordCondition::where('purchasing_info_record_id', $id)
            ->findOrFail($conditionId);

        $validated = $request->validate([
            'valid_from'       => 'sometimes|date',
            'valid_to'         => 'sometimes|nullable|date|after_or_equal:valid_from',
            'net_price'        => 'sometimes|numeric|min:0',
            'price_unit'       => 'sometimes|integer|min:1',
            'currency_code'    => 'sometimes|string|size:3',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active'        => 'sometimes|boolean',
        ]);

        $condition = $this->service->updateCondition($condition, $validated);

        return $this->success(
            new \App\Http\Resources\Purchase\PurchasingInfoRecordConditionResource($condition)
        );
    }

    /**
     * Deactivate a purchasing info record (sets is_active = false).
     */
    public function deactivate(string $id): JsonResponse
    {
        $record = PurchasingInfoRecord::findOrFail($id);
        $this->service->deactivate($record);

        return $this->success(null, 'Purchasing info record deactivated.');
    }

    /**
     * Return the effective price for a vendor–product pair on an optional date.
     * GET /info-records/price-for?vendor_id=1&product_id=2&date=2026-04-01
     */
    public function priceFor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'  => 'required|integer',
            'product_id' => 'required|integer',
            'date'       => 'nullable|date',
        ]);

        $price = $this->service->getPriceForVendorProduct(
            (int) $validated['vendor_id'],
            (int) $validated['product_id'],
            $validated['date'] ?? null
        );

        return $this->success([
            'vendor_id'       => (int) $validated['vendor_id'],
            'product_id'      => (int) $validated['product_id'],
            'date'            => $validated['date'] ?? now()->toDateString(),
            'effective_price' => $price,
        ]);
    }
}
