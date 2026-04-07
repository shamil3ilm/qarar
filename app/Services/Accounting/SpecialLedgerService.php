<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\SpecialLedger;
use App\Models\Accounting\SpecialLedgerEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpecialLedgerService
{
    /**
     * Post a journal entry into a specific special ledger.
     */
    public function postToLedger(JournalEntry $entry, int $ledgerId): void
    {
        $ledger = SpecialLedger::findOrFail($ledgerId);

        if (!$ledger->is_active) {
            throw new InvalidArgumentException("Special ledger [{$ledger->code}] is inactive.");
        }

        DB::transaction(function () use ($entry, $ledger): void {
            $period     = (int) $entry->entry_date->format('n');
            $fiscalYear = (int) $entry->entry_date->format('Y');

            foreach ($entry->lines as $line) {
                $accountId = $this->resolveTargetAccount($ledger, $line);

                $amountLocal = bcmul(
                    (string) $line->debit ?: (string) $line->credit,
                    (string) ($entry->exchange_rate ?? 1),
                    4
                );

                SpecialLedgerEntry::create([
                    'organization_id'  => $entry->organization_id,
                    'special_ledger_id' => $ledger->id,
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $accountId,
                    'posting_date'     => $entry->entry_date,
                    'amount'           => $line->debit > 0 ? $line->debit : $line->credit,
                    'currency_code'    => $entry->currency_code ?? $ledger->currency_code,
                    'exchange_rate'    => $entry->exchange_rate ?? 1,
                    'amount_local'     => $amountLocal,
                    'debit_credit'     => $line->debit > 0 ? 'D' : 'C',
                    'period'           => $period,
                    'fiscal_year'      => $fiscalYear,
                    'cost_center_id'   => $line->cost_center_id ?? null,
                    'profit_center_id' => null,
                    'reference_type'   => $entry->source_type,
                    'reference_id'     => $entry->source_id,
                ]);
            }
        });
    }

    /**
     * Post a journal entry to all active special ledgers for the organization.
     */
    public function postToAllLedgers(JournalEntry $entry): void
    {
        $ledgers = SpecialLedger::withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('is_active', true)
            ->get();

        foreach ($ledgers as $ledger) {
            $this->postToLedger($entry, $ledger->id);
        }
    }

    /**
     * Get aggregated balance for an account in a ledger for a given period string (YYYY-MM).
     *
     * @return array{debit: string, credit: string, balance: string}
     */
    public function getLedgerBalance(int $ledgerId, int $accountId, string $period): array
    {
        [$year, $mon] = array_pad(explode('-', $period), 2, null);

        $query = SpecialLedgerEntry::withoutGlobalScopes()
            ->where('special_ledger_id', $ledgerId)
            ->where('account_id', $accountId)
            ->where('fiscal_year', (int) $year);

        if ($mon !== null) {
            $query->where('period', (int) $mon);
        }

        $debit  = $query->clone()->where('debit_credit', 'D')->sum('amount_local');
        $credit = $query->clone()->where('debit_credit', 'C')->sum('amount_local');

        return [
            'debit'   => number_format((float) $debit, 4, '.', ''),
            'credit'  => number_format((float) $credit, 4, '.', ''),
            'balance' => number_format((float) $debit - (float) $credit, 4, '.', ''),
        ];
    }

    /**
     * Get a trial balance for a ledger at a specific year/period.
     */
    public function getTrialBalance(int $ledgerId, int $fiscalYear, int $period): Collection
    {
        return SpecialLedgerEntry::withoutGlobalScopes()
            ->select('account_id')
            ->selectRaw('SUM(CASE WHEN debit_credit = ? THEN amount_local ELSE 0 END) as total_debit', ['D'])
            ->selectRaw('SUM(CASE WHEN debit_credit = ? THEN amount_local ELSE 0 END) as total_credit', ['C'])
            ->selectRaw('SUM(CASE WHEN debit_credit = ? THEN amount_local ELSE -amount_local END) as balance', ['D'])
            ->where('special_ledger_id', $ledgerId)
            ->where('fiscal_year', $fiscalYear)
            ->where('period', '<=', $period)
            ->groupBy('account_id')
            ->with('account:id,code,name,account_type')
            ->get();
    }

    /**
     * Create a new special ledger.
     */
    public function createLedger(array $data): SpecialLedger
    {
        $this->validateLedgerData($data);

        return DB::transaction(function () use ($data): SpecialLedger {
            if (!empty($data['is_leading'])) {
                // Demote any existing leading ledger for the org
                SpecialLedger::withoutGlobalScopes()
                    ->where('organization_id', $data['organization_id'])
                    ->where('is_leading', true)
                    ->update(['is_leading' => false]);
            }

            return SpecialLedger::create($data);
        });
    }

    /**
     * Resolve the target account for a journal entry line using mapping rules.
     * Falls back to the original account if no rule matches.
     */
    private function resolveTargetAccount(SpecialLedger $ledger, JournalEntryLine $line): int
    {
        $account = Account::find($line->account_id);

        // Try specific account mapping first
        $rule = $ledger->mappingRules()
            ->where('is_active', true)
            ->where('source_account_id', $line->account_id)
            ->first();

        if ($rule && $rule->target_account_id) {
            return $rule->target_account_id;
        }

        // Try account-type mapping
        if ($account) {
            $typeRule = $ledger->mappingRules()
                ->where('is_active', true)
                ->whereNull('source_account_id')
                ->where('account_type', $account->account_type)
                ->first();

            if ($typeRule && $typeRule->target_account_id) {
                return $typeRule->target_account_id;
            }
        }

        // No mapping — use original account
        return $line->account_id;
    }

    private function validateLedgerData(array $data): void
    {
        if (empty($data['code'])) {
            throw new InvalidArgumentException('Ledger code is required.');
        }

        if (empty($data['name'])) {
            throw new InvalidArgumentException('Ledger name is required.');
        }

        $allowed = ['ifrs', 'gaap', 'local'];
        $principle = $data['accounting_principle'] ?? 'ifrs';
        if (!in_array($principle, $allowed, true)) {
            throw new InvalidArgumentException(
                "Invalid accounting_principle. Allowed: " . implode(', ', $allowed)
            );
        }
    }
}
