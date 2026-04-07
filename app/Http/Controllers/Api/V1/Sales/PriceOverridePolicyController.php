<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\PriceOverridePolicy;
use App\Services\Sales\PriceOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceOverridePolicyController extends Controller
{
    public function __construct(private PriceOverrideService $service) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->getPolicies(auth()->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'allow_price_change' => 'nullable|boolean',
            'allow_discount' => 'nullable|boolean',
            'allow_markup' => 'nullable|boolean',
            'allow_free_item' => 'nullable|boolean',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'max_markup_percent' => 'nullable|numeric|min:0|max:100',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_price_percent' => 'nullable|numeric|min:0|max:100',
            'max_total_discount_percent' => 'nullable|numeric|min:0|max:100',
            'requires_approval' => 'nullable|boolean',
            'approval_threshold_percent' => 'nullable|numeric|min:0|max:100',
            'approval_threshold_amount' => 'nullable|numeric|min:0',
            'requires_reason' => 'nullable|boolean',
            'applies_to' => 'nullable|string|in:all,roles,users,branches',
            'applicable_role_ids' => 'nullable|array',
            'applicable_user_ids' => 'nullable|array',
            'applicable_branch_ids' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data = array_merge($request->all(), [
            'organization_id' => auth()->user()->organization_id,
        ]);

        try {
            $policy = $this->service->createPolicy($data);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($policy);
    }

    public function show(PriceOverridePolicy $policy): JsonResponse
    {
        return $this->success($policy);
    }

    public function update(Request $request, PriceOverridePolicy $policy): JsonResponse
    {
        $policy->update($request->all());
        return $this->success($policy->fresh());
    }
}
