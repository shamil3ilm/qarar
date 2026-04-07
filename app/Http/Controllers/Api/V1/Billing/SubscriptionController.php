<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionAddon;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    public function current(): JsonResponse
    {
        $subscription = OrganizationSubscription::where('organization_id', auth()->user()->organization_id)
            ->with('plan')
            ->latest()
            ->first();

        if (!$subscription) {
            return $this->success([
                'id' => 0,
                'plan_id' => 0,
                'status' => 'none',
                'starts_at' => null,
                'ends_at' => null,
                'base_price' => '0.00',
                'max_users' => 0,
                'max_branches' => 0,
                'organization_id' => auth()->user()->organization_id,
            ]);
        }

        return $this->success($subscription);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'billing_cycle' => 'required|string|in:monthly,yearly,quarterly',
        ]);

        $subscription = $this->billingService->subscribeToPlan(
            auth()->user()->organization,
            $request->input('plan_id'),
            $request->all()
        );

        return $this->created($subscription->load('plan'));
    }

    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
        ]);

        $subscription = OrganizationSubscription::where('organization_id', auth()->user()->organization_id)
            ->where('status', 'active')
            ->firstOrFail();

        $updated = $this->billingService->changePlan($subscription, $request->input('plan_id'));
        return $this->success($updated->load('plan'));
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = OrganizationSubscription::where('organization_id', auth()->user()->organization_id)
            ->whereIn('status', ['active', 'trial'])
            ->first();

        if (!$subscription) {
            return $this->success(null, 'No active subscription to cancel');
        }

        $cancelled = $this->billingService->cancelSubscription($subscription, $request->input('reason', ''));
        return $this->success($cancelled);
    }

    public function availableAddons(): JsonResponse
    {
        $addons = SubscriptionAddon::where('is_active', true)->get();
        return $this->success($addons);
    }

    public function purchaseAddon(Request $request): JsonResponse
    {
        $request->validate([
            'addon_id' => 'required|integer|exists:subscription_addons,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $subscription = OrganizationSubscription::where('organization_id', auth()->user()->organization_id)
            ->where('status', 'active')
            ->firstOrFail();

        $addon = SubscriptionAddon::findOrFail($request->input('addon_id'));

        $purchase = $subscription->addonPurchases()->create([
            'addon_id' => $addon->id,
            'quantity' => $request->input('quantity', 1),
            'unit_price' => $addon->price,
            'total_price' => $addon->price * $request->input('quantity', 1),
            'starts_at' => now(),
            'status' => 'active',
        ]);

        return $this->created($purchase);
    }
}
