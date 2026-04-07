<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\VendorProductPricing;
use App\Services\Purchase\SourceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorPricingController extends Controller
{
    public function __construct(
        private SourceListService $sourceListService
    ) {}

    /**
     * List all vendor pricing records for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $records = VendorProductPricing::with(['vendor:id,name,email', 'product:id,name,sku'])
            ->when($request->product_id, fn ($q, $id) => $q->forProduct((int) $id))
            ->when($request->vendor_id, fn ($q, $id) => $q->forVendor((int) $id))
            ->when($request->preferred_only === 'true', fn ($q) => $q->preferredVendors())
            ->when($request->valid_only === 'true', fn ($q) => $q->valid())
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($records, null);
    }

    /**
     * Store a new vendor pricing record.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'product_id'                 => ['required', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'vendor_id'                  => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'vendor_product_code'        => 'nullable|string|max:100',
            'vendor_product_description' => 'nullable|string|max:500',
            'unit_price'                 => 'required|numeric|min:0',
            'currency_code'              => 'nullable|string|size:3',
            'lead_time_days'             => 'nullable|integer|min:0',
            'minimum_order_quantity'     => 'nullable|numeric|min:0',
            'order_quantity_multiple'    => 'nullable|numeric|min:0',
            'valid_from'                 => 'nullable|date',
            'valid_to'                   => 'nullable|date|after_or_equal:valid_from',
            'is_preferred_vendor'        => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $validated['organization_id'] = $orgId;

        $record = $this->sourceListService->createPricingRecord($validated);

        return $this->created(
            $record->load(['vendor:id,name,email', 'product:id,name,sku']),
            'Vendor pricing record created.'
        );
    }

    /**
     * Show a single vendor pricing record.
     */
    public function show(int $id): JsonResponse
    {
        $record = VendorProductPricing::with([
            'vendor:id,name,email',
            'product:id,name,sku',
            'vendorSourceListEntries',
        ])->find($id);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        return $this->success($record);
    }

    /**
     * Update a vendor pricing record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record = VendorProductPricing::find($id);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        $validated = $request->validate([
            'vendor_product_code'        => 'nullable|string|max:100',
            'vendor_product_description' => 'nullable|string|max:500',
            'unit_price'                 => 'sometimes|numeric|min:0',
            'currency_code'              => 'nullable|string|size:3',
            'lead_time_days'             => 'nullable|integer|min:0',
            'minimum_order_quantity'     => 'nullable|numeric|min:0',
            'order_quantity_multiple'    => 'nullable|numeric|min:0',
            'valid_from'                 => 'nullable|date',
            'valid_to'                   => 'nullable|date|after_or_equal:valid_from',
            'is_preferred_vendor'        => 'nullable|boolean',
            'notes'                      => 'nullable|string|max:2000',
        ]);

        $record = $this->sourceListService->updatePricingRecord($record, $validated);

        return $this->success(
            $record->load(['vendor:id,name,email', 'product:id,name,sku']),
            'Vendor pricing record updated.'
        );
    }

    /**
     * Delete a vendor pricing record.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = VendorProductPricing::find($id);

        if (!$record) {
            return $this->notFound('Vendor pricing record not found.');
        }

        $record->delete();

        return $this->success(null, 'Vendor pricing record deleted.');
    }

    /**
     * List all valid pricing records for a specific product, ordered by
     * preferred status then unit price ascending.
     */
    public function forProduct(int $productId): JsonResponse
    {
        $records = VendorProductPricing::forProduct($productId)
            ->valid()
            ->with(['vendor:id,name,email'])
            ->orderByDesc('is_preferred_vendor')
            ->orderBy('unit_price')
            ->get();

        return $this->success($records, 'Vendor pricing records retrieved.');
    }
}
