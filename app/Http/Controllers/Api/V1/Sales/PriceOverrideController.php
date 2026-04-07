<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\PriceOverride;
use App\Services\Sales\PriceOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceOverrideController extends Controller
{
    public function __construct(private PriceOverrideService $service) {}

    public function index(Request $request): JsonResponse
    {
        $overrides = PriceOverride::with('product', 'creator')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($overrides);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_id' => 'required|integer|exists:price_override_policies,id',
            'document_type' => 'required|string|in:invoice,quotation,sales_order,bill,purchase_order',
            'document_id' => 'nullable|integer',
            'line_item_id' => 'nullable|integer',
            'product_id' => 'nullable|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'original_price' => 'required|numeric|min:0',
            'override_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0.0001',
            'override_type' => 'required|string|in:discount,markup,custom_price,price_match,negotiated,manager_override',
            'reason_code' => 'nullable|string|max:30',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|integer|exists:contacts,id',
        ]);

        // Validate against policy limits
        $policy = \App\Models\Sales\PriceOverridePolicy::find($validated['policy_id']);
        if ($policy) {
            $discountPercent = $validated['original_price'] > 0
                ? (($validated['original_price'] - $validated['override_price']) / $validated['original_price']) * 100
                : 0;

            if ($discountPercent > 0 && !$policy->allow_discount) {
                return $this->error('Price discounts are not allowed by this policy.', 'POLICY_VIOLATION', 422);
            }

            if ($discountPercent < 0 && !$policy->allow_markup) {
                return $this->error('Price markups are not allowed by this policy.', 'POLICY_VIOLATION', 422);
            }

            if ($policy->max_discount_percent && $discountPercent > $policy->max_discount_percent) {
                return $this->error(
                    "Discount of {$discountPercent}% exceeds maximum allowed {$policy->max_discount_percent}%.",
                    'EXCEEDS_LIMIT',
                    422
                );
            }
        }

        // Calculate derived fields
        $validated['price_difference'] = round($validated['original_price'] - $validated['override_price'], 4);
        $validated['discount_percent'] = $validated['original_price'] > 0
            ? round(($validated['price_difference'] / $validated['original_price']) * 100, 2)
            : 0;
        $validated['total_impact'] = round($validated['price_difference'] * $validated['quantity'], 2);
        $validated['organization_id'] = auth()->user()->organization_id;
        $validated['created_by'] = auth()->id();
        $validated['document_id'] = $validated['document_id'] ?? 0;
        $validated['line_item_id'] = $validated['line_item_id'] ?? 0;

        // Determine approval status
        if ($policy && $policy->requires_approval) {
            $validated['approval_status'] = 'pending';
        } else {
            $validated['approval_status'] = 'auto_approved';
        }

        try {
            $override = $this->service->recordOverride($validated);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($override);
    }

    public function show(PriceOverride $override): JsonResponse
    {
        return $this->success($override->load('product', 'creator', 'approver', 'policy'));
    }

    public function approve(Request $request, PriceOverride $override): JsonResponse
    {
        if ($override->approval_status !== 'pending') {
            return $this->error('Only pending overrides can be approved.', 'INVALID_STATUS', 422);
        }

        $approved = $this->service->approveOverride($override->id, auth()->id(), $request->input('approval_notes'));
        return $this->success($approved);
    }

    public function reject(Request $request, PriceOverride $override): JsonResponse
    {
        $request->validate([
            'approval_notes' => 'required|string',
        ]);

        if ($override->approval_status !== 'pending') {
            return $this->error('Only pending overrides can be rejected.', 'INVALID_STATUS', 422);
        }

        $rejected = $this->service->rejectOverride($override->id, auth()->id(), $request->input('approval_notes'));
        return $this->success($rejected);
    }

    public function report(Request $request): JsonResponse
    {
        $report = $this->service->getOverrideReport(auth()->user()->organization_id, $request->all());
        return $this->success($report);
    }
}
