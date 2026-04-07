<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase\VendorSourceList;
use App\Services\Purchase\SourceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorSourceListController extends Controller
{
    public function __construct(
        private SourceListService $sourceListService
    ) {}

    /**
     * List all vendor source-list entries for the organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = VendorSourceList::with([
            'vendor:id,name,email',
            'product:id,name,sku',
            'pricingRecord:id,uuid,unit_price,currency_code,lead_time_days',
        ])
            ->when($request->product_id, fn ($q, $id) => $q->forProduct((int) $id))
            ->when($request->vendor_id, fn ($q, $id) => $q->where('vendor_id', (int) $id))
            ->when($request->active_only === 'true', fn ($q) => $q->active())
            ->byPriority()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($entries, null);
    }

    /**
     * Store a new vendor source-list entry.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'product_id'               => ['required', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'vendor_id'                => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'vendor_product_pricing_id' => 'nullable|exists:vendor_product_pricing,id',
            'plant_code'               => 'nullable|string|max:50',
            'valid_from'               => 'nullable|date',
            'valid_to'                 => 'nullable|date|after_or_equal:valid_from',
            'is_fixed_vendor'          => 'nullable|boolean',
            'is_blocked'               => 'nullable|boolean',
            'priority'                 => 'nullable|integer|min:1',
            'quota_percentage'         => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['organization_id'] = $orgId;

        $entry = $this->sourceListService->createSourceListEntry($validated);

        return $this->created(
            $entry->load(['vendor:id,name,email', 'product:id,name,sku']),
            'Vendor source list entry created.'
        );
    }

    /**
     * Show a single vendor source-list entry.
     */
    public function show(int $id): JsonResponse
    {
        $entry = VendorSourceList::with([
            'vendor:id,name,email',
            'product:id,name,sku',
            'pricingRecord',
        ])->find($id);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        return $this->success($entry);
    }

    /**
     * Update a vendor source-list entry.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entry = VendorSourceList::find($id);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        $validated = $request->validate([
            'vendor_product_pricing_id' => 'nullable|exists:vendor_product_pricing,id',
            'plant_code'                => 'nullable|string|max:50',
            'valid_from'                => 'nullable|date',
            'valid_to'                  => 'nullable|date|after_or_equal:valid_from',
            'is_fixed_vendor'           => 'nullable|boolean',
            'is_blocked'                => 'nullable|boolean',
            'priority'                  => 'nullable|integer|min:1',
            'quota_percentage'          => 'nullable|numeric|min:0|max:100',
        ]);

        $entry = $this->sourceListService->updateSourceListEntry($entry, $validated);

        return $this->success(
            $entry->load(['vendor:id,name,email', 'product:id,name,sku']),
            'Vendor source list entry updated.'
        );
    }

    /**
     * Delete a vendor source-list entry.
     */
    public function destroy(int $id): JsonResponse
    {
        $entry = VendorSourceList::find($id);

        if (!$entry) {
            return $this->notFound('Vendor source list entry not found.');
        }

        $entry->delete();

        return $this->success(null, 'Vendor source list entry deleted.');
    }

    /**
     * Return ordered approved vendors (with pricing) for a given product.
     */
    public function vendorsForProduct(int $productId): JsonResponse
    {
        $vendors = $this->sourceListService->getVendorsForProduct($productId);

        return $this->success($vendors, 'Approved vendors for product retrieved.');
    }
}
