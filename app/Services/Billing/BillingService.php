<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Billing\BillingInvoice;
use App\Models\Billing\DiscountCode;
use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Billing\UsageMetric;
use App\Models\Billing\UsageSnapshot;
use App\Models\Core\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function getPlans(): mixed
    {
        return SubscriptionPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('display_order')
            ->get();
    }

    public function subscribeToPlan(Organization $organization, int $planId, array $data = []): OrganizationSubscription
    {
        return DB::transaction(function () use ($organization, $planId, $data) {
            $plan = SubscriptionPlan::findOrFail($planId);

            $subscription = OrganizationSubscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? 'trial' : 'active',
                'starts_at' => now(),
                'ends_at' => $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'base_price' => $plan->base_price,
                'max_users' => $plan->max_users,
                'max_branches' => $plan->max_branches,
                'storage_limit_mb' => $plan->storage_limit_mb,
                'max_invoices_per_month' => $plan->max_invoices_per_month,
                'enabled_modules' => $plan->included_modules,
                'enabled_features' => $plan->features,
                'auto_renew' => $data['auto_renew'] ?? true,
                'next_billing_date' => $plan->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'discount_code' => $data['discount_code'] ?? null,
            ]);

            return $subscription;
        });
    }

    public function cancelSubscription(OrganizationSubscription $subscription, string $reason): OrganizationSubscription
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);

        return $subscription->fresh();
    }

    public function changePlan(OrganizationSubscription $subscription, int $newPlanId): OrganizationSubscription
    {
        return DB::transaction(function () use ($subscription, $newPlanId) {
            $newPlan = SubscriptionPlan::findOrFail($newPlanId);

            $subscription->update([
                'plan_id' => $newPlan->id,
                'base_price' => $newPlan->base_price,
                'max_users' => $newPlan->max_users,
                'max_branches' => $newPlan->max_branches,
                'storage_limit_mb' => $newPlan->storage_limit_mb,
                'max_invoices_per_month' => $newPlan->max_invoices_per_month,
                'enabled_modules' => $newPlan->included_modules,
                'enabled_features' => $newPlan->features,
            ]);

            return $subscription->fresh();
        });
    }

    public function recordUsage(int $organizationId, string $metricType, int $quantity): UsageMetric
    {
        return UsageMetric::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'metric_type' => $metricType,
                'metric_date' => now()->toDateString(),
            ],
            [
                'quantity' => DB::raw('quantity + ' . (int) $quantity),
                'billing_period' => now()->format('Y-m'),
            ]
        );
    }

    public function generateInvoice(OrganizationSubscription $subscription): BillingInvoice
    {
        return DB::transaction(function () use ($subscription) {
            $invoice = BillingInvoice::create([
                'invoice_number' => 'BILL-' . strtoupper(Str::random(8)),
                'organization_id' => $subscription->organization_id,
                'subscription_id' => $subscription->id,
                'billing_period_start' => now()->startOfMonth(),
                'billing_period_end' => now()->endOfMonth(),
                'invoice_date' => now(),
                'due_date' => now()->addDays(15),
                'currency_code' => 'USD',
                'subtotal' => $subscription->base_price,
                'discount_amount' => $subscription->discount_amount,
                'tax_amount' => 0,
                'total' => $subscription->base_price - $subscription->discount_amount,
                'amount_paid' => 0,
                'amount_due' => $subscription->base_price - $subscription->discount_amount,
                'status' => 'draft',
            ]);

            return $invoice;
        });
    }

    public function getUsageSummary(int $organizationId): ?UsageSnapshot
    {
        return UsageSnapshot::where('organization_id', $organizationId)->first();
    }

    public function validateDiscountCode(string $code, int $organizationId): ?DiscountCode
    {
        $discount = DiscountCode::where('code', $code)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->first();

        if (!$discount) {
            return null;
        }

        if ($discount->max_uses && $discount->times_used >= $discount->max_uses) {
            return null;
        }

        $orgUsage = $discount->usages()->where('organization_id', $organizationId)->count();
        if ($orgUsage >= $discount->max_uses_per_org) {
            return null;
        }

        return $discount;
    }
}
