<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentRun;
use App\Models\Accounting\PaymentRunItem;
use App\Models\Concerns\ChecksIdempotency;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentRunService
{
    use ChecksIdempotency;

    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Paginate payment runs with optional filters.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = PaymentRun::with(['createdBy:id,name', 'approvedBy:id,name', 'bankAccount:id,account_name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_direction'])) {
            $query->where('payment_direction', $filters['payment_direction']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Propose a payment run: create the run and query open bills/invoices in date range.
     */
    public function propose(array $data): PaymentRun
    {
        return DB::transaction(function () use ($data) {
            $run = PaymentRun::create([
                'organization_id'    => $data['organization_id'],
                'run_reference'      => $data['run_reference'],
                'payment_direction'  => $data['payment_direction'] ?? PaymentRun::DIRECTION_OUTGOING,
                'payment_date'       => $data['payment_date'],
                'due_date_from'      => $data['due_date_from'] ?? null,
                'due_date_to'        => $data['due_date_to'] ?? null,
                'vendor_filter'      => $data['vendor_filter'] ?? null,
                'payment_methods'    => $data['payment_methods'] ?? null,
                'minimum_payment'    => $data['minimum_payment'] ?? 0,
                'currency_code'      => $data['currency_code'] ?? 'SAR',
                'bank_account_id'    => $data['bank_account_id'] ?? null,
                'status'             => PaymentRun::STATUS_DRAFT,
                'created_by'         => $data['created_by'],
            ]);

            $items = $this->buildProposedItems($run);

            $totalItems  = count($items);
            $totalAmount = array_sum(array_column($items, 'payment_amount'));

            if ($totalItems > 0) {
                PaymentRunItem::insert($items);
            }

            $run->transitionTo(PaymentRun::STATUS_PROPOSED, [
                'total_items'  => $totalItems,
                'total_amount' => $totalAmount,
            ]);

            return $run->fresh(['items', 'createdBy', 'bankAccount']);
        });
    }

    /**
     * Approve a proposed payment run.
     */
    public function approve(PaymentRun $run): PaymentRun
    {
        if ($run->status !== PaymentRun::STATUS_PROPOSED) {
            throw new InvalidArgumentException('Only proposed payment runs can be approved.');
        }

        $run->transitionTo(PaymentRun::STATUS_APPROVED, [
            'approved_by' => auth()->id(),
        ]);

        return $run->fresh();
    }

    /**
     * Post the payment run: create payments for all included items.
     *
     * Idempotent: a duplicate call for the same run within 24 h returns the
     * already-posted run without re-marking bills/invoices as paid.
     */
    public function post(PaymentRun $run, ?string $idempotencyKey = null): PaymentRun
    {
        if ($run->status !== PaymentRun::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved payment runs can be posted.');
        }

        /** @var PaymentRun $result */
        $result = $this->withFinancialIdempotency(
            key: $idempotencyKey ?? "payment_run:{$run->id}:post",
            operation: 'payment_run.post',
            orgId: $run->organization_id,
            callback: fn (): PaymentRun => $this->executePost($run),
        );

        return $result;
    }

    /**
     * Core post logic — wrapped by post() idempotency guard.
     */
    private function executePost(PaymentRun $run): PaymentRun
    {
        return DB::transaction(function () use ($run): PaymentRun {
            $status = $run->items()->where('status', PaymentRunItem::STATUS_INCLUDED)->exists()
                ? PaymentRunItem::STATUS_INCLUDED
                : PaymentRunItem::STATUS_PROPOSED;

            $run->items()->where('status', $status)->chunkById(100, function ($items) use ($run): void {
                foreach ($items as $item) {
                    $this->postItem($run, $item);
                }
            });

            $run->transitionTo(PaymentRun::STATUS_POSTED);

            return $run->fresh(['items']);
        });
    }

    /**
     * Exclude a single item from the payment run with a reason.
     */
    public function excludeItem(PaymentRunItem $item, string $reason): PaymentRunItem
    {
        $run = $item->paymentRun;

        if (!in_array($run->status, [PaymentRun::STATUS_PROPOSED, PaymentRun::STATUS_APPROVED], true)) {
            throw new InvalidArgumentException('Items can only be excluded from proposed or approved runs.');
        }

        $item->update([
            'status'           => PaymentRunItem::STATUS_EXCLUDED,
            'exclusion_reason' => $reason,
        ]);

        // Recalculate run totals (DB aggregation, no collection load).
        $included = $run->items()
            ->whereIn('status', [PaymentRunItem::STATUS_PROPOSED, PaymentRunItem::STATUS_INCLUDED])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(payment_amount), 0) as total')
            ->first();

        $run->update([
            'total_items'  => (int) ($included->cnt ?? 0),
            'total_amount' => (float) ($included->total ?? 0),
        ]);

        return $item->fresh();
    }

    /**
     * Cancel a payment run (only draft/proposed/approved).
     */
    public function cancel(PaymentRun $run): PaymentRun
    {
        if (in_array($run->status, [PaymentRun::STATUS_POSTED, PaymentRun::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('Posted or already cancelled runs cannot be cancelled.');
        }

        $run->transitionTo(PaymentRun::STATUS_CANCELLED);

        return $run->fresh();
    }

    /**
     * Build proposed payment items from open bills (outgoing) or invoices (incoming).
     */
    private function buildProposedItems(PaymentRun $run): array
    {
        $items     = [];
        $now       = now();
        $minAmount = (float) $run->minimum_payment;

        if ($run->payment_direction === PaymentRun::DIRECTION_OUTGOING) {
            $billQuery = Bill::where('organization_id', $run->organization_id)
                ->whereIn('status', ['approved', 'partial'])
                ->where('amount_due', '>', $minAmount)
                ->whereHas('supplier', fn ($q) => $q->where('payment_block', false));

            if ($run->due_date_from) {
                $billQuery->whereDate('due_date', '>=', $run->due_date_from);
            }
            if ($run->due_date_to) {
                $billQuery->whereDate('due_date', '<=', $run->due_date_to);
            }
            if (!empty($run->vendor_filter)) {
                $billQuery->whereIn('supplier_id', $run->vendor_filter);
            }

            $billQuery->chunkById(100, function ($bills) use ($run, $now, &$items): void {
                foreach ($bills as $bill) {
                    $items[] = [
                        'payment_run_id'   => $run->id,
                        'document_type'    => PaymentRunItem::DOC_TYPE_BILL,
                        'document_id'      => $bill->id,
                        'vendor_id'        => $bill->supplier_id,
                        'open_amount'      => $bill->amount_due,
                        'payment_amount'   => $bill->amount_due,
                        'discount_taken'   => 0,
                        'due_date'         => $bill->due_date,
                        'status'           => PaymentRunItem::STATUS_PROPOSED,
                        'exclusion_reason' => null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            });
        } else {
            $invoiceQuery = Invoice::where('organization_id', $run->organization_id)
                ->whereIn('status', ['sent', 'partial'])
                ->where('amount_due', '>', $minAmount)
                ->whereHas('customer', fn ($q) => $q->where('payment_block', false));

            if ($run->due_date_from) {
                $invoiceQuery->whereDate('due_date', '>=', $run->due_date_from);
            }
            if ($run->due_date_to) {
                $invoiceQuery->whereDate('due_date', '<=', $run->due_date_to);
            }

            $invoiceQuery->chunkById(100, function ($invoices) use ($run, $now, &$items): void {
                foreach ($invoices as $invoice) {
                    $items[] = [
                        'payment_run_id'   => $run->id,
                        'document_type'    => PaymentRunItem::DOC_TYPE_INVOICE,
                        'document_id'      => $invoice->id,
                        'vendor_id'        => $invoice->customer_id,
                        'open_amount'      => $invoice->amount_due,
                        'payment_amount'   => $invoice->amount_due,
                        'discount_taken'   => 0,
                        'due_date'         => $invoice->due_date,
                        'status'           => PaymentRunItem::STATUS_PROPOSED,
                        'exclusion_reason' => null,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
            });
        }

        return $items;
    }

    /**
     * Post a single payment run item: mark document as paid.
     */
    private function postItem(PaymentRun $run, PaymentRunItem $item): void
    {
        if ($item->document_type === PaymentRunItem::DOC_TYPE_BILL) {
            $bill = Bill::find($item->document_id);
            if ($bill) {
                $bill->update(['status' => 'paid', 'amount_due' => 0]);
            }
        } elseif ($item->document_type === PaymentRunItem::DOC_TYPE_INVOICE) {
            $invoice = Invoice::find($item->document_id);
            if ($invoice) {
                $invoice->update(['status' => 'paid', 'amount_due' => 0]);
            }
        }

        $item->update(['status' => PaymentRunItem::STATUS_PAID]);
    }
}
