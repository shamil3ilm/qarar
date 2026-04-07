<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\BillingPlan;
use App\Models\Sales\BillingPlanItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingPlanService
{
    public function list(int $orgId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = BillingPlan::forOrganization($orgId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['plan_type'])) {
            $query->where('plan_type', $filters['plan_type']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        return $query->with(['salesOrder', 'quotation'])->latest()->paginate($perPage);
    }

    public function create(array $data): BillingPlan
    {
        return DB::transaction(function () use ($data): BillingPlan {
            $plan = BillingPlan::create($data);

            if ($plan->plan_type === BillingPlan::TYPE_PERIODIC && !empty($data['auto_generate_items'])) {
                $this->generatePeriodicItems($plan);
            }

            return $plan->load(['salesOrder', 'quotation', 'items']);
        });
    }

    public function update(BillingPlan $plan, array $data): BillingPlan
    {
        $plan->update($data);
        return $plan->fresh(['salesOrder', 'quotation', 'items']);
    }

    public function addItem(BillingPlan $plan, array $data): BillingPlanItem
    {
        $data['billing_plan_id'] = $plan->id;
        $data['organization_id'] = $plan->organization_id;

        return DB::transaction(function () use ($plan, $data): BillingPlanItem {
            $item = BillingPlanItem::create($data);
            $this->recalculateBilledValue($plan);
            return $item;
        });
    }

    public function updateItem(BillingPlanItem $item, array $data): BillingPlanItem
    {
        $item->update($data);
        $this->recalculateBilledValue($item->billingPlan);
        return $item->fresh();
    }

    public function generatePeriodicItems(BillingPlan $plan): void
    {
        if ($plan->plan_type !== BillingPlan::TYPE_PERIODIC) {
            return;
        }
        if (empty($plan->start_date) || empty($plan->end_date) || empty($plan->periodic_interval_days)) {
            return;
        }

        $current = Carbon::parse($plan->start_date);
        $end = Carbon::parse($plan->end_date);
        $intervalDays = $plan->periodic_interval_days;
        $sortOrder = 0;

        DB::transaction(function () use ($plan, $current, $end, $intervalDays, &$sortOrder): void {
            while ($current->lte($end)) {
                BillingPlanItem::create([
                    'organization_id' => $plan->organization_id,
                    'billing_plan_id' => $plan->id,
                    'billing_date' => $current->toDateString(),
                    'billing_amount' => $plan->total_value,
                    'status' => BillingPlanItem::STATUS_PENDING,
                    'sort_order' => $sortOrder++,
                ]);
                $current->addDays($intervalDays);
            }
        });
    }

    public function billItem(BillingPlanItem $item, int $invoiceId): BillingPlanItem
    {
        return DB::transaction(function () use ($item, $invoiceId): BillingPlanItem {
            $item->update([
                'status' => BillingPlanItem::STATUS_BILLED,
                'invoice_id' => $invoiceId,
                'billed_at' => now(),
            ]);

            $plan = $item->billingPlan;
            $this->recalculateBilledValue($plan);

            // Check if all items are billed → complete the plan
            $pendingCount = $plan->items()->where('status', BillingPlanItem::STATUS_PENDING)->count();
            if ($pendingCount === 0) {
                $plan->update(['status' => BillingPlan::STATUS_COMPLETED]);
            }

            return $item->fresh(['invoice']);
        });
    }

    public function getDueItems(int $orgId): Collection
    {
        return BillingPlanItem::forOrganization($orgId)
            ->where('status', BillingPlanItem::STATUS_PENDING)
            ->where('billing_date', '<=', Carbon::today())
            ->with(['billingPlan'])
            ->get();
    }

    private function recalculateBilledValue(BillingPlan $plan): void
    {
        $billedValue = $plan->items()
            ->where('status', BillingPlanItem::STATUS_BILLED)
            ->sum('billing_amount');

        $plan->update(['billed_value' => $billedValue]);
    }
}
