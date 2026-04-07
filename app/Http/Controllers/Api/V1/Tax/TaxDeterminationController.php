<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\TaxDeterminationRule;
use App\Services\Tax\TaxDeterminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxDeterminationController extends Controller
{
    public function __construct(
        private readonly TaxDeterminationService $taxDeterminationService,
    ) {}

    /**
     * List tax determination rules.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = $this->taxDeterminationService->list($request->only([
            'document_type',
            'is_active',
            'per_page',
        ]));

        return $this->paginated($rules);
    }

    /**
     * Create a new tax determination rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'document_type'     => 'required|in:sales_invoice,purchase_bill,sales_order,purchase_order,all',
            'from_country_code' => 'nullable|string|size:2',
            'to_country_code'   => 'nullable|string|size:2',
            'from_region'       => 'nullable|string|max:100',
            'to_region'         => 'nullable|string|max:100',
            'tax_category_id'   => 'nullable|exists:tax_categories,id',
            'customer_type'     => 'nullable|in:b2b,b2c,government,exempt,any',
            'tax_type'          => 'required|in:standard,zero,exempt,reverse_charge,out_of_scope',
            'tax_rate_id'       => 'nullable|exists:tax_rates,id',
            'is_reverse_charge' => 'boolean',
            'priority'          => 'nullable|integer|min:1|max:65535',
            'is_active'         => 'boolean',
            'valid_from'        => 'nullable|date',
            'valid_to'          => 'nullable|date|after_or_equal:valid_from',
        ]);

        $validated['created_by'] = auth()->id();

        $rule = $this->taxDeterminationService->create($validated);

        return $this->created($rule->load(['taxCategory', 'taxRate']), 'Tax determination rule created.');
    }

    /**
     * Show a specific tax determination rule.
     */
    public function show(TaxDeterminationRule $taxDeterminationRule): JsonResponse
    {
        return $this->success($taxDeterminationRule->load(['taxCategory', 'taxRate']));
    }

    /**
     * Update a tax determination rule.
     */
    public function update(Request $request, TaxDeterminationRule $taxDeterminationRule): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'description'       => 'nullable|string',
            'document_type'     => 'sometimes|in:sales_invoice,purchase_bill,sales_order,purchase_order,all',
            'from_country_code' => 'nullable|string|size:2',
            'to_country_code'   => 'nullable|string|size:2',
            'from_region'       => 'nullable|string|max:100',
            'to_region'         => 'nullable|string|max:100',
            'tax_category_id'   => 'nullable|exists:tax_categories,id',
            'customer_type'     => 'nullable|in:b2b,b2c,government,exempt,any',
            'tax_type'          => 'sometimes|in:standard,zero,exempt,reverse_charge,out_of_scope',
            'tax_rate_id'       => 'nullable|exists:tax_rates,id',
            'is_reverse_charge' => 'boolean',
            'priority'          => 'nullable|integer|min:1|max:65535',
            'is_active'         => 'boolean',
            'valid_from'        => 'nullable|date',
            'valid_to'          => 'nullable|date|after_or_equal:valid_from',
        ]);

        $rule = $this->taxDeterminationService->update($taxDeterminationRule, $validated);

        return $this->success($rule, 'Tax determination rule updated.');
    }

    /**
     * Delete a tax determination rule.
     */
    public function destroy(TaxDeterminationRule $taxDeterminationRule): JsonResponse
    {
        $this->taxDeterminationService->delete($taxDeterminationRule);

        return $this->success(null, 'Tax determination rule deleted.');
    }

    /**
     * Simulate tax determination for a given line context.
     * Useful for frontend previews before creating a document.
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type'   => 'required|in:sales_invoice,purchase_bill,sales_order,purchase_order,all',
            'from_country'    => 'nullable|string|size:2',
            'to_country'      => 'nullable|string|size:2',
            'from_region'     => 'nullable|string|max:100',
            'to_region'       => 'nullable|string|max:100',
            'tax_category_id' => 'nullable|exists:tax_categories,id',
            'customer_type'   => 'nullable|in:b2b,b2c,government,exempt,any',
            'amount'          => 'required|numeric|min:0',
        ]);

        $amount = (float) $validated['amount'];
        unset($validated['amount']);

        $determination = $this->taxDeterminationService->determineForLine($validated);
        $calculation   = $this->taxDeterminationService->calculateTax($amount, $determination);

        $rule = $determination['rule_id']
            ? TaxDeterminationRule::with(['taxCategory', 'taxRate'])->find($determination['rule_id'])
            : null;

        return $this->success([
            'determination' => $determination,
            'calculation'   => $calculation,
            'matched_rule'  => $rule,
        ]);
    }
}
