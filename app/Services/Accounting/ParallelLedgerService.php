<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\SpecialLedger;
use App\Models\Accounting\SpecialLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Parallel Ledger Service — SAP FI parallel accounting (FAGL_MIG).
 *
 * SAP supports multiple accounting principles (IFRS, local GAAP, tax) as
 * parallel ledgers.  When a journal entry is posted to the leading ledger,
 * it can fan out to one or more parallel ledgers using account mapping rules.
 *
 * This service:
 *   1. Resolves active parallel ledgers for the organisation
 *   2. Applies account mapping rules (optional account substitution per ledger)
 *   3. Creates `SpecialLedgerEntry` records linked to the source JournalEntry
 *
 * Accounting principles supported:
 *   - IFRS  (International Financial Reporting Standards)
 *   - LOCAL (Local GAAP — the leading ledger)
 *   - TAX   (Tax-basis accounting)
 *   - MGMT  (Management accounting — non-statutory)
 */
class ParallelLedgerService
{
    /**
     * Fan a posted journal entry out to all active non-leading parallel ledgers.
     *
     * Called automatically after every JournalEntry posting via
     * the FanOutToParallelLedgers listener, or explicitly for backdated entries.
     */
    public function fanOut(JournalEntry $journalEntry): void
    {
        if ($journalEntry->status !== JournalEntry::STATUS_POSTED) {
            return;
        }

        $ledgers = SpecialLedger::active()
            ->where('organization_id', $journalEntry->organization_id)
            ->where('is_leading', false)
            ->with('mappingRules.sourceAccount', 'mappingRules.targetAccount')
            ->get();

        if ($ledgers->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($journalEntry, $ledgers): void {
            foreach ($ledgers as $ledger) {
                $this->postToLedger($journalEntry, $ledger);
            }
        });
    }

    /**
     * Manually post a journal entry to a specific ledger (e.g. for adjustments).
     */
    public function postToLedger(JournalEntry $journalEntry, SpecialLedger $ledger): void
    {
        // Idempotency: skip if already posted to this ledger
        $alreadyExists = SpecialLedgerEntry::where('special_ledger_id', $ledger->id)
            ->where('journal_entry_id', $journalEntry->id)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        foreach ($journalEntry->lines as $line) {
            $targetAccountId = $this->resolveAccount($line->account_id, $ledger);

            SpecialLedgerEntry::create([
                'organization_id'  => $journalEntry->organization_id,
                'special_ledger_id' => $ledger->id,
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $targetAccountId,
                'posting_date'     => $journalEntry->entry_date,
                'amount'           => $line->debit > 0 ? $line->debit : $line->credit,
                'currency_code'    => $journalEntry->currency_code,
                'exchange_rate'    => $journalEntry->exchange_rate ?? 1,
                'amount_local'     => ($line->debit > 0 ? $line->debit : $line->credit) * ($journalEntry->exchange_rate ?? 1),
                'debit_credit'     => $line->debit > 0 ? 'D' : 'C',
                'period'           => (int) $journalEntry->entry_date->format('m'),
                'fiscal_year'      => (int) $journalEntry->entry_date->format('Y'),
                'cost_center_id'   => $line->cost_center_id ?? null,
                'profit_center_id' => $line->profit_center_id ?? null,
                'reference_type'   => $journalEntry->source_type ?? null,
                'reference_id'     => $journalEntry->source_id ?? null,
            ]);
        }
    }

    /**
     * Get a parallel ledger balance report: leading vs. parallel comparison.
     *
     * @return array{
     *     ledger: SpecialLedger,
     *     balances: array<string, array{leading: float, parallel: float, variance: float}>
     * }
     */
    public function getParallelComparison(
        int $organizationId,
        int $ledgerId,
        string $fiscalYear,
        ?string $period = null,
    ): array {
        $ledger = SpecialLedger::findOrFail($ledgerId);

        $parallelQuery = SpecialLedgerEntry::where('special_ledger_id', $ledgerId)
            ->where('organization_id', $organizationId)
            ->where('fiscal_year', $fiscalYear);

        $leadingQuery = SpecialLedgerEntry::whereHas(
            'specialLedger',
            fn ($q) => $q->where('is_leading', true)->where('organization_id', $organizationId)
        )->where('organization_id', $organizationId)->where('fiscal_year', $fiscalYear);

        if ($period !== null) {
            $parallelQuery->where('period', $period);
            $leadingQuery->where('period', $period);
        }

        $parallelBalances = $parallelQuery->get()->groupBy('account_id');
        $leadingBalances  = $leadingQuery->get()->groupBy('account_id');

        $accountIds = $parallelBalances->keys()->merge($leadingBalances->keys())->unique();

        $comparison = [];
        foreach ($accountIds as $accountId) {
            $account = Account::find($accountId);
            if (! $account) {
                continue;
            }

            $leadingAmount  = $this->sumEntries($leadingBalances[$accountId] ?? collect());
            $parallelAmount = $this->sumEntries($parallelBalances[$accountId] ?? collect());

            $comparison[$account->code] = [
                'account_name' => $account->name,
                'leading'      => $leadingAmount,
                'parallel'     => $parallelAmount,
                'variance'     => round($parallelAmount - $leadingAmount, 2),
            ];
        }

        return ['ledger' => $ledger, 'balances' => $comparison];
    }

    /**
     * List all configured ledgers for an organisation.
     */
    public function getLedgers(int $organizationId)
    {
        return SpecialLedger::where('organization_id', $organizationId)
            ->with('mappingRules')
            ->orderByDesc('is_leading')
            ->get();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Resolve the target account for a given ledger.
     * If a mapping rule exists, substitute the account; otherwise pass through.
     */
    private function resolveAccount(int $sourceAccountId, SpecialLedger $ledger): int
    {
        $rule = $ledger->mappingRules->firstWhere('source_account_id', $sourceAccountId);

        return $rule?->target_account_id ?? $sourceAccountId;
    }

    private function sumEntries(\Illuminate\Support\Collection $entries): float
    {
        return (float) $entries->sum(function ($entry) {
            return $entry->debit_credit === 'D' ? $entry->amount : -$entry->amount;
        });
    }
}
