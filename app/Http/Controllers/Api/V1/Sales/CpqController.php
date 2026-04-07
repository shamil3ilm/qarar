<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\CpqConfigurableProduct;
use App\Models\Sales\CpqConfiguration;
use App\Models\Sales\CpqConstraintRule;
use App\Models\Sales\CpqOption;
use App\Models\Sales\CpqOptionGroup;
use App\Models\Sales\CpqPricingRule;
use App\Services\Sales\CpqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CpqController extends Controller
{
    public function __construct(
        private readonly CpqService $cpqService
    ) {}

    // -------------------------------------------------------------------------
    // Configurable Products
    // -------------------------------------------------------------------------

    public function products(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $products = CpqConfigurableProduct::where('organization_id', $orgId)
            ->when($request->boolean('active_only', true), fn ($q) => $q->active())
            ->with(['product', 'optionGroups.options'])
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($products);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'                   => 'required|integer|exists:products,id',
            'name'                         => 'required|string|max:255',
            'description'                  => 'nullable|string',
            'base_price'                   => 'required|numeric|min:0',
            'currency_code'                => 'required|string|size:3',
            'configuration_validity_days'  => 'nullable|integer|min:1|max:3650',
            'is_active'                    => 'nullable|boolean',
        ]);

        $product = CpqConfigurableProduct::create(array_merge(
            $validated,
            ['organization_id' => $this->organizationId($request)]
        ));

        return $this->created($product->load('product'));
    }

    public function showProduct(int $id): JsonResponse
    {
        $product = CpqConfigurableProduct::with([
            'product',
            'optionGroups.options',
            'pricingRules',
            'constraintRules.ifOption',
            'constraintRules.thenOption',
        ])->findOrFail($id);

        return $this->success($product);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $product   = CpqConfigurableProduct::findOrFail($id);
        $validated = $request->validate([
            'name'                         => 'sometimes|string|max:255',
            'description'                  => 'nullable|string',
            'base_price'                   => 'sometimes|numeric|min:0',
            'currency_code'                => 'sometimes|string|size:3',
            'configuration_validity_days'  => 'nullable|integer|min:1|max:3650',
            'is_active'                    => 'nullable|boolean',
        ]);

        $product->update($validated);

        return $this->success($product->fresh('product'));
    }

    // -------------------------------------------------------------------------
    // Option Groups & Options
    // -------------------------------------------------------------------------

    public function optionGroups(int $productId): JsonResponse
    {
        $groups = CpqOptionGroup::where('cpq_configurable_product_id', $productId)
            ->with('options')
            ->orderBy('sort_order')
            ->get();

        return $this->success($groups);
    }

    public function storeOptionGroup(Request $request, int $productId): JsonResponse
    {
        CpqConfigurableProduct::findOrFail($productId); // guard

        $validated = $request->validate([
            'group_code'     => 'required|string|max:30',
            'name'           => 'required|string|max:255',
            'selection_type' => 'nullable|in:single,multi',
            'is_required'    => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);

        $group = CpqOptionGroup::create(array_merge(
            $validated,
            ['cpq_configurable_product_id' => $productId]
        ));

        return $this->created($group);
    }

    public function storeOption(Request $request, int $groupId): JsonResponse
    {
        CpqOptionGroup::findOrFail($groupId); // guard

        $validated = $request->validate([
            'option_code'          => 'required|string|max:30',
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'price_modifier_type'  => 'nullable|in:fixed,percentage,none',
            'price_modifier_value' => 'nullable|numeric',
            'is_default'           => 'nullable|boolean',
            'is_active'            => 'nullable|boolean',
            'sort_order'           => 'nullable|integer',
            'linked_product_id'    => 'nullable|integer|exists:products,id',
        ]);

        $option = CpqOption::create(array_merge(
            $validated,
            ['cpq_option_group_id' => $groupId]
        ));

        return $this->created($option);
    }

    // -------------------------------------------------------------------------
    // Pricing Rules
    // -------------------------------------------------------------------------

    public function pricingRules(int $productId): JsonResponse
    {
        $rules = CpqPricingRule::where('cpq_configurable_product_id', $productId)
            ->orderBy('priority')
            ->get();

        return $this->success($rules);
    }

    public function storePricingRule(Request $request, int $productId): JsonResponse
    {
        CpqConfigurableProduct::findOrFail($productId); // guard

        $validated = $request->validate([
            'rule_name'      => 'required|string|max:255',
            'condition_json' => 'nullable|array',
            'discount_type'  => 'required|in:percentage,fixed,price_override',
            'discount_value' => 'required|numeric|min:0',
            'priority'       => 'nullable|integer|min:0|max:9999',
            'valid_from'     => 'nullable|date',
            'valid_to'       => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'nullable|boolean',
        ]);

        $rule = CpqPricingRule::create(array_merge(
            $validated,
            ['cpq_configurable_product_id' => $productId]
        ));

        return $this->created($rule);
    }

    // -------------------------------------------------------------------------
    // Constraint Rules
    // -------------------------------------------------------------------------

    public function constraintRules(int $productId): JsonResponse
    {
        $rules = CpqConstraintRule::where('cpq_configurable_product_id', $productId)
            ->with(['ifOption', 'thenOption'])
            ->get();

        return $this->success($rules);
    }

    public function storeConstraintRule(Request $request, int $productId): JsonResponse
    {
        CpqConfigurableProduct::findOrFail($productId); // guard

        $validated = $request->validate([
            'rule_type'     => 'required|in:requires,excludes,includes',
            'if_option_id'  => 'nullable|integer|exists:cpq_options,id',
            'then_option_id' => 'nullable|integer|exists:cpq_options,id',
            'error_message' => 'nullable|string|max:200',
            'is_active'     => 'nullable|boolean',
        ]);

        $rule = CpqConstraintRule::create(array_merge(
            $validated,
            ['cpq_configurable_product_id' => $productId]
        ));

        return $this->created($rule->load(['ifOption', 'thenOption']));
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function configure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'       => 'required|integer|exists:cpq_configurable_products,id',
            'selected_options' => 'required|array|min:1',
            'selected_options.*' => 'integer|exists:cpq_options,id',
        ]);

        $result = $this->cpqService->configure(
            $validated['product_id'],
            $validated['selected_options']
        );

        return $this->success($result);
    }

    public function configurations(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $configs = CpqConfiguration::where('organization_id', $orgId)
            ->with(['configurableProduct', 'contact', 'items.option'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($configs);
    }

    public function saveConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cpq_configurable_product_id'    => 'required|integer|exists:cpq_configurable_products,id',
            'contact_id'                     => 'nullable|integer|exists:contacts,id',
            'currency_code'                  => 'nullable|string|size:3',
            'selected_options'               => 'required|array|min:1',
            'selected_options.*.option_id'   => 'required|integer|exists:cpq_options,id',
            'selected_options.*.option_group_id' => 'required|integer|exists:cpq_option_groups,id',
            'selected_options.*.quantity'    => 'nullable|numeric|min:0.0001',
        ]);

        $config = $this->cpqService->saveConfiguration(array_merge(
            $validated,
            [
                'organization_id' => $this->organizationId($request),
                'created_by'      => auth()->id(),
            ]
        ));

        return $this->created($config);
    }

    public function showConfiguration(int $id): JsonResponse
    {
        $config = CpqConfiguration::with([
            'configurableProduct.product',
            'contact',
            'items.option',
            'items.optionGroup',
            'quotation',
        ])->findOrFail($id);

        return $this->success($config);
    }

    public function convertToQuotation(Request $request, int $id): JsonResponse
    {
        $config    = CpqConfiguration::findOrFail($id);
        $validated = $request->validate([
            'salesperson_id'       => 'nullable|integer|exists:users,id',
            'notes'                => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
        ]);

        $quotation = $this->cpqService->convertToQuotation($config, $validated);

        return $this->created($quotation->load('lines'));
    }
}
