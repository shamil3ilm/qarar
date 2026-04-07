<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\DeliverySplitRule;
use App\Models\Sales\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverySplitController extends Controller
{
    /**
     * GET /api/v1/sales/delivery-split-rules
     * List all delivery split rules.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DeliverySplitRule::latest()
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->when($request->has('split_criteria'), fn($q) => $q->where('split_criteria', $request->string('split_criteria')));

        $rules = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($rules);
    }

    /**
     * POST /api/v1/sales/delivery-split-rules
     * Create a new delivery split rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'                      => 'required|string|max:100',
            'split_criteria'                 => 'required|in:warehouse,delivery_date,route,weight,volume',
            'applies_to'                     => 'required|in:all_customers,customer_group,specific_customer',
            'applies_to_id'                  => 'nullable|integer',
            'allow_partial_delivery'         => 'nullable|boolean',
            'minimum_delivery_quantity_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'                      => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $rule = DeliverySplitRule::create($validated);

        return $this->success($rule, 'Delivery split rule created.', 201);
    }

    /**
     * GET /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function show(DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        return $this->success($deliverySplitRule);
    }

    /**
     * PUT /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function update(Request $request, DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        $validated = $request->validate([
            'rule_name'                      => 'sometimes|string|max:100',
            'split_criteria'                 => 'sometimes|in:warehouse,delivery_date,route,weight,volume',
            'applies_to'                     => 'sometimes|in:all_customers,customer_group,specific_customer',
            'applies_to_id'                  => 'nullable|integer',
            'allow_partial_delivery'         => 'nullable|boolean',
            'minimum_delivery_quantity_pct'  => 'nullable|numeric|min:0|max:100',
            'is_active'                      => 'nullable|boolean',
        ]);

        $deliverySplitRule->update($validated);

        return $this->success($deliverySplitRule->fresh(), 'Delivery split rule updated.');
    }

    /**
     * DELETE /api/v1/sales/delivery-split-rules/{deliverySplitRule}
     */
    public function destroy(DeliverySplitRule $deliverySplitRule): JsonResponse
    {
        $deliverySplitRule->delete();

        return $this->success(null, 'Delivery split rule deleted.');
    }

    /**
     * POST /api/v1/sales/delivery-split-rules/apply
     * Apply active delivery split rules to a sales order or shipment.
     *
     * Returns a breakdown of how lines should be split for delivery.
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type'   => 'required|in:sales_order,shipment',
            'source_id'     => 'required|integer',
            'customer_id'   => 'required|integer|exists:contacts,id',
            'customer_group_id' => 'nullable|integer',
        ]);

        $orgId       = (int) $this->organizationId($request);
        $customerId  = (int) $validated['customer_id'];
        $customerGroupId = isset($validated['customer_group_id'])
            ? (int) $validated['customer_group_id']
            : null;

        // Load active rules applicable to this customer
        $rules = DeliverySplitRule::active()
            ->where('organization_id', $orgId)
            ->get()
            ->filter(fn ($rule) => $rule->appliesTo($customerId, $customerGroupId))
            ->values();

        if ($rules->isEmpty()) {
            return $this->success([
                'source_type'     => $validated['source_type'],
                'source_id'       => $validated['source_id'],
                'applicable_rules' => [],
                'split_required'   => false,
                'message'          => 'No applicable delivery split rules found.',
            ]);
        }

        // Build the applied-rules response
        $appliedRules = $rules->map(fn ($rule) => [
            'rule_id'                       => $rule->id,
            'rule_name'                     => $rule->rule_name,
            'split_criteria'                => $rule->split_criteria,
            'allow_partial_delivery'        => $rule->allow_partial_delivery,
            'minimum_delivery_quantity_pct' => (float) $rule->minimum_delivery_quantity_pct,
        ])->all();

        return $this->success([
            'source_type'      => $validated['source_type'],
            'source_id'        => $validated['source_id'],
            'applicable_rules' => $appliedRules,
            'split_required'   => true,
        ], 'Delivery split rules applied.');
    }
}
