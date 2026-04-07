<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\RebateAccrual;
use App\Models\Sales\RebateMaster;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RebateAccrualService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Calculate the rebate amount for a given invoice amount against a rebate master.
     * Applies minimum purchase threshold and maximum rebate cap.
     */
    public function calculateRebate(RebateMaster $rebate, float $invoiceAmount): float
    {
        if ($rebate->minimum_purchase !== null && $invoiceAmount < (float) $rebate->minimum_purchase) {
            return 0.0;
        }

        $calculated = match ($rebate->rebate_type) {
            RebateMaster::TYPE_PERCENTAGE  => $invoiceAmount * ((float) $rebate->rebate_rate / 100),
            RebateMaster::TYPE_FIXED_AMOUNT => (float) $rebate->rebate_rate,
            default                         => 0.0,
        };

        if ($rebate->maximum_rebate !== null) {
            $calculated = min($calculated, (float) $rebate->maximum_rebate);
        }

        return round($calculated, 4);
    }

    /**
     * Accrue rebates for a sent invoice.
     * Finds all active, valid RebateMasters for the invoice customer and creates accrual records.
     *
     * @return RebateAccrual[]
     */
    public function accrueForInvoice(Invoice $invoice): array
    {
        $accruals = [];

        $rebates = RebateMaster::where('organization_id', $invoice->organization_id)
            ->where('contact_id', $invoice->customer_id)
            ->where('accrual_method', RebateMaster::METHOD_ON_INVOICE)
            ->validOn($invoice->invoice_date ?? now())
            ->get();

        foreach ($rebates as $rebate) {
            $invoiceAmount = (float) $invoice->total;
            $rebateAmount  = $this->calculateRebate($rebate, $invoiceAmount);

            if ($rebateAmount <= 0.0) {
                continue;
            }

            $accrual = DB::transaction(function () use ($rebate, $invoice, $invoiceAmount, $rebateAmount): RebateAccrual {
                $record = RebateAccrual::create([
                    'rebate_master_id' => $rebate->id,
                    'invoice_id'       => $invoice->id,
                    'accrual_date'     => $invoice->invoice_date ?? now()->toDateString(),
                    'invoice_amount'   => $invoiceAmount,
                    'rebate_amount'    => $rebateAmount,
                    'status'           => RebateAccrual::STATUS_PENDING,
                ]);

                // Auto-post the journal if accounts are configured
                if ($rebate->expense_account_id && $rebate->accrual_account_id) {
                    $this->postAccrualJournal($record);
                }

                return $record->fresh();
            });

            $accruals[] = $accrual;
        }

        return $accruals;
    }

    /**
     * Post the GL journal entry for an accrual record.
     * Debit: Rebate Expense, Credit: Rebate Accrual Liability
     */
    public function postAccrualJournal(RebateAccrual $accrual): RebateAccrual
    {
        if ($accrual->isPosted()) {
            throw new InvalidArgumentException('Accrual journal has already been posted.');
        }

        $rebate = $accrual->rebateMaster;

        if (!$rebate->expense_account_id || !$rebate->accrual_account_id) {
            throw new InvalidArgumentException(
                "Rebate master #{$rebate->id} is missing expense or accrual account configuration."
            );
        }

        return DB::transaction(function () use ($accrual, $rebate): RebateAccrual {
            $journal = $this->journalService->create(
                [
                    'organization_id' => $rebate->organization_id,
                    'entry_date'      => $accrual->accrual_date->toDateString(),
                    'reference'       => "REBATE-ACCRUAL-{$accrual->id}",
                    'description'     => "Rebate accrual for invoice #{$accrual->invoice_id} — {$rebate->name}",
                    'source_type'     => RebateAccrual::class,
                    'source_id'       => $accrual->id,
                    'created_by'      => auth()->id(),
                ],
                [
                    [
                        'account_id'  => $rebate->expense_account_id,
                        'debit'       => $accrual->rebate_amount,
                        'credit'      => 0,
                        'description' => "Rebate expense — {$rebate->name}",
                    ],
                    [
                        'account_id'  => $rebate->accrual_account_id,
                        'debit'       => 0,
                        'credit'      => $accrual->rebate_amount,
                        'description' => "Rebate accrual liability — {$rebate->name}",
                    ],
                ]
            );

            $accrual->update([
                'journal_entry_id' => $journal->id,
                'status'           => RebateAccrual::STATUS_POSTED,
            ]);

            return $accrual->fresh();
        });
    }

    /**
     * Settle a rebate by creating a clearing journal entry.
     * Debit: Rebate Accrual Liability, Credit: Cash/AP account (passed via data).
     */
    public function settleRebate(RebateMaster $rebate, float $amount, int $userId): void
    {
        if (!$rebate->expense_account_id || !$rebate->accrual_account_id) {
            throw new InvalidArgumentException(
                "Rebate master #{$rebate->id} is missing account configuration for settlement."
            );
        }

        DB::transaction(function () use ($rebate, $amount, $userId): void {
            // Mark posted accruals as settled up to the settlement amount
            $remaining = $amount;

            $accruals = $rebate->accruals()
                ->where('status', RebateAccrual::STATUS_POSTED)
                ->orderBy('accrual_date')
                ->get();

            foreach ($accruals as $accrual) {
                if ($remaining <= 0.0) {
                    break;
                }

                $accrual->update(['status' => RebateAccrual::STATUS_SETTLED]);
                $remaining = round($remaining - (float) $accrual->rebate_amount, 4);
            }

            // Post settlement journal: Debit Accrual Liability, Credit Expense (reverse)
            $this->journalService->create(
                [
                    'organization_id' => $rebate->organization_id,
                    'entry_date'      => now()->toDateString(),
                    'reference'       => "REBATE-SETTLE-{$rebate->id}",
                    'description'     => "Rebate settlement — {$rebate->name}",
                    'source_type'     => RebateMaster::class,
                    'source_id'       => $rebate->id,
                    'created_by'      => $userId,
                ],
                [
                    [
                        'account_id'  => $rebate->accrual_account_id,
                        'debit'       => $amount,
                        'credit'      => 0,
                        'description' => "Settlement of rebate accrual — {$rebate->name}",
                    ],
                    [
                        'account_id'  => $rebate->expense_account_id,
                        'debit'       => 0,
                        'credit'      => $amount,
                        'description' => "Settlement clearing — {$rebate->name}",
                    ],
                ]
            );
        });
    }
}
