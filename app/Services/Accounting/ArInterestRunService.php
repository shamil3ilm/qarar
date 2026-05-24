<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AR Interest Calculation on Arrears (SAP FI-AR F.24/F.26).
 *
 * Calculates interest charges on overdue customer invoices and posts
 * journal entries: DR Accounts Receivable / CR Interest Income.
 */
class ArInterestRunService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Preview interest charges without posting anything.
     *
     * @return array{lines: array[], total_interest: float, invoice_count: int}
     */
    public function preview(int $organizationId, array $params): array
    {
        $invoices = $this->selectOverdueInvoices($organizationId, $params);

        $lines        = [];
        $totalInterest = 0.0;

        foreach ($invoices as $invoice) {
            $daysOverdue  = $invoice->getDaysPastDue();
            $annualRate   = (float) ($params['annual_rate'] ?? 12.0);   // default 12% p.a.
            $interest     = $this->calculateInterest(
                (float) $invoice->amount_due,
                $daysOverdue,
                $annualRate
            );

            if ($interest <= 0) {
                continue;
            }

            $totalInterest += $interest;

            $lines[] = [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'contact_name'   => $invoice->contact?->company_name ?? $invoice->contact?->contact_name,
                'due_date'       => $invoice->due_date?->toDateString(),
                'days_overdue'   => $daysOverdue,
                'outstanding'    => (float) $invoice->amount_due,
                'annual_rate'    => $annualRate,
                'interest'       => round($interest, 2),
            ];
        }

        return [
            'lines'          => $lines,
            'total_interest' => round($totalInterest, 2),
            'invoice_count'  => count($lines),
        ];
    }

    /**
     * Execute the interest run — calculates and posts GL journal entries.
     *
     * @return array{lines: array[], total_interest: float, journal_entries_posted: int}
     */
    public function execute(int $organizationId, int $branchId, array $params, int $_userId): array
    {
        $preview = $this->preview($organizationId, $params);

        if (empty($preview['lines'])) {
            return array_merge($preview, ['journal_entries_posted' => 0]);
        }

        $arAccount      = Account::where('organization_id', $organizationId)
            ->where('sub_type', Account::SUBTYPE_RECEIVABLE)
            ->where('is_active', true)
            ->first();

        $incomeAccount  = Account::where('organization_id', $organizationId)
            ->where('sub_type', Account::SUBTYPE_OTHER_INCOME)
            ->where('is_active', true)
            ->first();

        if (! $arAccount || ! $incomeAccount) {
            return array_merge($preview, [
                'journal_entries_posted' => 0,
                'warning' => 'AR or Interest Income account not configured — no entries posted.',
            ]);
        }

        $posted = 0;

        DB::transaction(function () use ($preview, $arAccount, $incomeAccount, $organizationId, $branchId, &$posted): void {
            $runDate = now()->toDateString();

            foreach ($preview['lines'] as $line) {
                if ($line['interest'] <= 0) {
                    continue;
                }

                $entry = $this->journalService->createSimpleEntry(
                    organizationId: $organizationId,
                    branchId:       $branchId,
                    debitAccountId: $arAccount->id,
                    creditAccountId: $incomeAccount->id,
                    amount:         $line['interest'],
                    description:    "Interest on overdue invoice {$line['invoice_number']} ({$line['days_overdue']} days overdue)",
                    reference:      "INT-{$line['invoice_number']}",
                    date:           $runDate
                );

                $this->journalService->postEntry($entry);
                $posted++;
            }
        });

        return array_merge($preview, ['journal_entries_posted' => $posted]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function selectOverdueInvoices(int $organizationId, array $params): Collection
    {
        $query = Invoice::with('contact:id,company_name,contact_name')
            ->where('organization_id', $organizationId)
            ->where('status', Invoice::STATUS_OVERDUE)
            ->where('amount_due', '>', 0)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());

        if (! empty($params['contact_id'])) {
            $query->where('contact_id', $params['contact_id']);
        }

        if (! empty($params['min_days_overdue'])) {
            // due_date <= today - min_days → at least that many days past due
            $query->where('due_date', '<=', now()->subDays((int) $params['min_days_overdue'])->toDateString());
        }

        if (! empty($params['max_days_overdue'])) {
            // due_date >= today - max_days → no more than max_days past due
            $query->where('due_date', '>=', now()->subDays((int) $params['max_days_overdue'])->toDateString());
        }

        return $query->get();
    }

    /**
     * Simple/360 interest calculation (SAP default).
     * interest = principal × (annual_rate / 100 / 365) × days
     */
    private function calculateInterest(float $principal, int $days, float $annualRate): float
    {
        if ($principal <= 0 || $days <= 0 || $annualRate <= 0) {
            return 0.0;
        }

        return $principal * ($annualRate / 100.0 / 365.0) * $days;
    }
}
