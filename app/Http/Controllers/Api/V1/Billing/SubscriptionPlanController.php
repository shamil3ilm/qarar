<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('display_order')
            ->get();

        return $this->success($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = SubscriptionPlan::create($request->all());
        return $this->created($plan);
    }

    public function show(SubscriptionPlan $plan): JsonResponse
    {
        return $this->success($plan->load('meteredPricingTiers'));
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($request->all());
        return $this->success($plan->fresh());
    }

    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        $plan->delete();
        return $this->success(['message' => 'Plan deleted']);
    }
}
