<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(FeatureFlag::orderBy('code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:feature_flags,code',
            'is_enabled' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'rollout_type' => 'nullable|string|max:30',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'specific_organization_ids' => 'nullable|array',
        ]);

        $flag = FeatureFlag::create($request->all());
        return $this->created($flag);
    }

    public function show(FeatureFlag $flag): JsonResponse
    {
        return $this->success($flag);
    }

    public function update(Request $request, FeatureFlag $flag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:feature_flags,code,' . $flag->id,
            'is_enabled' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'rollout_type' => 'nullable|string|max:30',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'specific_organization_ids' => 'nullable|array',
            'specific_subscription_plans' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $flag->update($validated);
        return $this->success($flag->fresh());
    }

    public function toggle(FeatureFlag $flag): JsonResponse
    {
        $flag->update(['is_enabled' => !$flag->is_enabled]);
        return $this->success($flag->fresh());
    }

    public function checkFlag(string $code): JsonResponse
    {
        $flag = FeatureFlag::where('code', $code)->first();

        if (!$flag) {
            return $this->notFound('Feature flag not found');
        }

        return $this->success($flag);
    }
}
