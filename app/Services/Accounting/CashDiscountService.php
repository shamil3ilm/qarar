<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\PaymentTerm;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CashDiscountService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Apply an early-payment cash discount to an AR invoice.
     *
     * Posts:
     *   DR  Cash Discount Expense (discountGlAccountId)
     *   CR  AR receivable (invoice's GL account, falling back to discountGlAccountId)
     */
    public function applyArDiscount(
        int $organizationId,
        int $invoiceId,
        int $paymentTermId,
        string $paymentDate,
        int $discountGlAccountId
    ): array {
        return DB::transaction(function () use (
            $organizationId,
            $invoiceId,
            $paymentTermId,
            $paymentDate,
            $discountGlAccountId
        ): array {
            $term    = PaymentTerm::where('organization_id', $organizationId)->findOrFail($paymentTermId);
            $invoice = Invoice::where('organization_id', $organizationId)->lockForUpdate()->findOrFail($invoiceId);

            $outstanding = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 4);
            $invoiceDate = Carbon::parse($invoice->invoice_date ?? $invoice->created_at);
            $payDate     = Carbon::parse($paymentDate);

            if (!$term->isEligibleForDiscount($payDate, $invoiceDate)) {
                return ['eligible' => false, 'discount_amount' => '0.0000'];
            }

            $discountAmount = $term->calculateDiscount($outstanding);

            // Determine AR GL account: prefer the invoice's journal entry AR side,
            // fall back to the supplied discount GL account.
            $arAccountId = $invoice->gl_account_id ?? $discountGlAccountId;

            $entry = $this->journalService->createEntry(
                [
                    'organization_id' => $organizationId,
                    'entry_date'      => $paymentDate,
                    'description'     => "Cash discount on invoice {$invoice->invoice_number}",
                    'reference'       => "DISC-AR-{$invoice->invoice_number}",
                ],
                [
                    [
                        'account_id'  => $discountGlAccountId,
                        'debit'       => (float) $discountAmount,
                        'credit'      => 0.0,
                        'description' => 'Cash discount expense',
                        'line_order'  => 0,
                    ],
                    [
                        'account_id'  => $arAccountId,
                        'debit'       => 0.0,
                        'credit'      => (float) $discountAmount,
                        'description' => "Discount {$term->discount_pct}% on {$invoice->invoice_number}",
                        'line_order'  => 1,
                    ],
                ]
            );

            $this->journalService->postEntry($entry);

            // Reduce outstanding on invoice
            $newAmountPaid = bcadd((string) $invoice->amount_paid, $discountAmount, 4);
            $newAmountDue  = bcsub((string) $invoice->total, $newAmountPaid, 4);
            $newStatus     = bccomp($newAmountPaid, (string) $invoice->total, 4) >= 0
                ? Invoice::STATUS_PAID
                : Invoice::STATUS_PARTIAL;

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => max('0.0000', $newAmountDue),
                'status'      => $newStatus,
            ]);

            return [
                'eligible'         => true,
                'discount_amount'  => $discountAmount,
                'discount_pct'     => $term->discount_pct,
                'journal_entry_id' => $entry->id,
            ];
        });
    }

    /**
     * Preview AR discount amount without posting.
     */
    public function previewArDiscount(
        int $organizationId,
        int $invoiceId,
        int $paymentTermId,
        string $paymentDate
    ): array {
        $term    = PaymentTerm::where('organization_id', $organizationId)->findOrFail($paymentTermId);
        $invoice = Invoice::where('organization_id', $organizationId)->findOrFail($invoiceId);

        $outstanding = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 4);
        $invoiceDate = Carbon::parse($invoice->invoice_date ?? $invoice->created_at);
        $payDate     = Carbon::parse($paymentDate);

        $eligible       = $term->isEligibleForDiscount($payDate, $invoiceDate);
        $discountAmount = $eligible ? $term->calculateDiscount($outstanding) : '0.0000';

        return [
            'eligible'        => $eligible,
            'discount_pct'    => $term->discount_pct,
            'discount_days'   => $term->discount_days,
            'outstanding'     => $outstanding,
            'discount_amount' => $discountAmount,
            'net_payable'     => bcsub($outstanding, $discountAmount, 4),
        ];
    }

    /**
     * List all active payment terms for the organization.
     */
    public function getPaymentTerms(int $organizationId): Collection
    {
        return PaymentTerm::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    /**
     * Create a new payment term for the organization.
     */
    public function createPaymentTerm(int $organizationId, array $data): PaymentTerm
    {
        return PaymentTerm::create(array_merge($data, ['organization_id' => $organizationId]));
    }
}
