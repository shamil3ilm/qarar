<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\BillingPlan;
use App\Models\Sales\BillingPlanItem;
use App\Models\Sales\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VF04 Billing Due List — SAP SD equivalent.
 *
 * Collects all billing-plan items that have reached their billing date
 * but have not yet been invoiced.  Supports individual and collective
 * billing runs (create one invoice per item, or consolidate by customer).
 */
class BillingDueListService
{
    /**
     * Return paginated due-billing items, optionally filtered.
     *
     * Filters:
     *   - billing_date_from / billing_date_to  : narrow the date window
     *   - sales_order_id                       : single order
     *   - customer_id                          : all orders for a customer
     *   - plan_type (milestone|periodic)
     */
    public function getDueList(int $organizationId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = BillingPlanItem::query()
            ->where('billing_plan_items.organization_id', $organizationId)
            ->where('billing_plan_items.status', BillingPlanItem::STATUS_PENDING)
            ->where('billing_plan_items.billing_date', '<=', Carbon::today())
            ->join('billing_plans', 'billing_plans.id', '=', 'billing_plan_items.billing_plan_id')
            ->where('billing_plans.status', BillingPlan::STATUS_ACTIVE)
            ->with([
                'billingPlan.salesOrder.contact:id,name',
                'billingPlan.salesOrder:id,uuid,order_number,contact_id',
            ])
            ->select('billing_plan_items.*');

        if (! empty($filters['billing_date_from'])) {
            $query->where('billing_plan_items.billing_date', '>=', $filters['billing_date_from']);
        }

        if (! empty($filters['billing_date_to'])) {
            $query->where('billing_plan_items.billing_date', '<=', $filters['billing_date_to']);
        }

        if (! empty($filters['sales_order_id'])) {
            $query->where('billing_plans.sales_order_id', $filters['sales_order_id']);
        }

        if (! empty($filters['plan_type'])) {
            $query->where('billing_plans.plan_type', $filters['plan_type']);
        }

        if (! empty($filters['customer_id'])) {
            $query->join('sales_orders', 'sales_orders.id', '=', 'billing_plans.sales_order_id')
                ->where('sales_orders.contact_id', $filters['customer_id']);
        }

        return $query->orderBy('billing_plan_items.billing_date')->paginate($perPage);
    }

    /**
     * Bill a single due item — creates an invoice for the item amount.
     */
    public function billItem(BillingPlanItem $item, int $createdByUserId): Invoice
    {
        if (! $item->isDue()) {
            throw new \RuntimeException("Item {$item->id} is not due for billing.");
        }

        return DB::transaction(function () use ($item, $createdByUserId): Invoice {
            $plan = $item->billingPlan()->with('salesOrder')->firstOrFail();

            $invoice = Invoice::create([
                'organization_id'  => $item->organization_id,
                'contact_id'       => $plan->salesOrder->contact_id,
                'sales_order_id'   => $plan->sales_order_id,
                'invoice_date'     => today()->toDateString(),
                'due_date'         => today()->addDays(30)->toDateString(),
                'currency'         => $plan->billing_currency,
                'subtotal'         => $item->billing_amount,
                'tax_amount'       => 0,
                'total_amount'     => $item->billing_amount,
                'status'           => 'draft',
                'notes'            => "Billing plan item: {$item->milestone_description}",
                'created_by'       => $createdByUserId,
            ]);

            $item->update([
                'status'     => BillingPlanItem::STATUS_BILLED,
                'invoice_id' => $invoice->id,
                'billed_at'  => now(),
            ]);

            $this->updatePlanBilledValue($plan);

            return $invoice;
        });
    }

    /**
     * Collective billing run — bill all due items, grouped by customer into
     * one invoice per customer (SAP VF04 collective billing behaviour).
     *
     * @param  int[]  $itemIds  Subset of due items; bills ALL due items if empty.
     * @return Invoice[]
     */
    public function collectiveBillingRun(int $organizationId, array $itemIds = [], int $createdByUserId = 0): array
    {
        $query = BillingPlanItem::query()
            ->where('organization_id', $organizationId)
            ->where('status', BillingPlanItem::STATUS_PENDING)
            ->where('billing_date', '<=', Carbon::today())
            ->with(['billingPlan.salesOrder']);

        if (! empty($itemIds)) {
            $query->whereIn('id', $itemIds);
        }

        $items = $query->get();

        // Group by customer
        $byCustomer = $items->groupBy(fn (BillingPlanItem $i) => $i->billingPlan->salesOrder->contact_id);

        $invoices = [];

        DB::transaction(function () use ($byCustomer, $organizationId, $createdByUserId, &$invoices): void {
            foreach ($byCustomer as $customerId => $customerItems) {
                $firstItem = $customerItems->first();
                $plan      = $firstItem->billingPlan;
                $total     = $customerItems->sum('billing_amount');

                $invoice = Invoice::create([
                    'organization_id' => $organizationId,
                    'contact_id'      => $customerId,
                    'sales_order_id'  => $plan->sales_order_id,
                    'invoice_date'    => today()->toDateString(),
                    'due_date'        => today()->addDays(30)->toDateString(),
                    'currency'        => $plan->billing_currency,
                    'subtotal'        => $total,
                    'tax_amount'      => 0,
                    'total_amount'    => $total,
                    'status'          => 'draft',
                    'notes'           => "Collective billing run — {$customerItems->count()} item(s)",
                    'created_by'      => $createdByUserId,
                ]);

                foreach ($customerItems as $item) {
                    $item->update([
                        'status'     => BillingPlanItem::STATUS_BILLED,
                        'invoice_id' => $invoice->id,
                        'billed_at'  => now(),
                    ]);
                    $this->updatePlanBilledValue($item->billingPlan);
                }

                $invoices[] = $invoice;
            }
        });

        return $invoices;
    }

    // ----------------------------------------------------------------

    private function updatePlanBilledValue(BillingPlan $plan): void
    {
        $billedValue = $plan->items()
            ->where('status', BillingPlanItem::STATUS_BILLED)
            ->sum('billing_amount');

        $status = $billedValue >= $plan->total_value
            ? BillingPlan::STATUS_COMPLETED
            : BillingPlan::STATUS_ACTIVE;

        $plan->update([
            'billed_value' => $billedValue,
            'status'       => $status,
        ]);
    }
}
