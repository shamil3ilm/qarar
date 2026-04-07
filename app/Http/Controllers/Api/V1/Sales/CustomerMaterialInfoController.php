<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\CustomerMaterialInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMaterialInfoController extends Controller
{
    /**
     * GET /api/v1/sales/customer-material-infos
     * List customer material infos with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerMaterialInfo::with(['contact', 'product'])
            ->latest()
            ->when($request->has('contact_id'), fn($q) => $q->forContact($request->integer('contact_id')))
            ->when($request->has('product_id'), fn($q) => $q->forProduct($request->integer('product_id')))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->has('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(function ($q) use ($term) {
                    $q->where('customer_material_number', 'like', "%{$term}%")
                        ->orWhere('customer_material_description', 'like', "%{$term}%");
                });
            });

        $items = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($items);
    }

    /**
     * POST /api/v1/sales/customer-material-infos
     * Create a new customer material info record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'                    => 'required|integer|exists:contacts,id',
            'product_id'                    => 'required|integer|exists:products,id',
            'customer_material_number'      => 'nullable|string|max:100',
            'customer_material_description' => 'nullable|string|max:255',
            'delivery_lead_time_days'       => 'nullable|integer|min:0',
            'minimum_order_quantity'        => 'nullable|numeric|min:0',
            'unit_of_measure'               => 'nullable|string|max:20',
            'notes'                         => 'nullable|string|max:5000',
            'is_active'                     => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $info = CustomerMaterialInfo::create($validated);

        return $this->success($info->load(['contact', 'product']), 'Customer material info created.', 201);
    }

    /**
     * GET /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function show(CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        return $this->success($customerMaterialInfo->load(['contact', 'product']));
    }

    /**
     * PUT /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function update(Request $request, CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        $validated = $request->validate([
            'customer_material_number'      => 'nullable|string|max:100',
            'customer_material_description' => 'nullable|string|max:255',
            'delivery_lead_time_days'       => 'nullable|integer|min:0',
            'minimum_order_quantity'        => 'nullable|numeric|min:0',
            'unit_of_measure'               => 'nullable|string|max:20',
            'notes'                         => 'nullable|string|max:5000',
            'is_active'                     => 'nullable|boolean',
        ]);

        $customerMaterialInfo->update($validated);

        return $this->success($customerMaterialInfo->fresh(['contact', 'product']), 'Customer material info updated.');
    }

    /**
     * DELETE /api/v1/sales/customer-material-infos/{customerMaterialInfo}
     */
    public function destroy(CustomerMaterialInfo $customerMaterialInfo): JsonResponse
    {
        $customerMaterialInfo->delete();

        return $this->success(null, 'Customer material info deleted.');
    }

    /**
     * GET /api/v1/sales/customer-material-infos/lookup?customer_id=&product_id=
     * Cross-reference lookup — find internal product by customer material number or product_id.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'               => 'required|integer|exists:contacts,id',
            'product_id'               => 'nullable|integer|exists:products,id',
            'customer_material_number' => 'nullable|string|max:100',
        ]);

        $query = CustomerMaterialInfo::with(['product'])
            ->forContact((int) $validated['contact_id'])
            ->active();

        if (!empty($validated['product_id'])) {
            $query->forProduct((int) $validated['product_id']);
        }

        if (!empty($validated['customer_material_number'])) {
            $query->where('customer_material_number', $validated['customer_material_number']);
        }

        $result = $query->first();

        if ($result === null) {
            return $this->error('No customer material info found for the given criteria.', 404);
        }

        return $this->success($result);
    }
}
