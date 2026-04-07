<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Core\CacheService;
use Illuminate\Support\Facades\DB;

class AccountBalanceService
{
    public function __construct(
        private readonly CacheService $cache,
    ) {}

    /**
     * Get balance for a single account.
     */
    public function getAccountBalance(
        int $accountId,
        ?int $fiscalYearId = null,
        ?string $asOfDate = null,
        bool $includeOpening = true
    ): array {
        // Resolve the account first (lightweight PK lookup) so we have org_id for the cache key.
        $account = Account::findOrFail($accountId);
        $orgId   = (int) $account->organization_id;

        $cacheKey = "account_balance:{$accountId}:fy{$fiscalYearId}:d{$asOfDate}:ob" . ($includeOpening ? '1' : '0');

        return $this->cache->rememberTransact($orgId, $cacheKey, function () use ($account, $accountId, $fiscalYearId, $asOfDate, $includeOpening): array {
            $movementQuery = JournalEntryLine::query()
                ->where('account_id', $accountId)
                ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $asOfDate) {
                    $q->where('status', 'posted');

                    if ($fiscalYearId) {
                        $q->where('fiscal_year_id', $fiscalYearId);
                    }

                    if ($asOfDate) {
                        $q->whereDate('entry_date', '<=', $asOfDate);
                    }
                });

            $totals = (clone $movementQuery)
                ->selectRaw('COALESCE(SUM(base_debit), 0) as total_debit, COALESCE(SUM(base_credit), 0) as total_credit')
                ->first();

            $openingBalance = 0;
            if ($includeOpening && $fiscalYearId) {
                $opening = $account->openingBalances()
                    ->where('fiscal_year_id', $fiscalYearId)
                    ->first();

                if ($opening) {
                    $openingBalance = $account->isDebitNormal()
                        ? $opening->debit - $opening->credit
                        : $opening->credit - $opening->debit;
                }
            }

            $movementBalance = $account->isDebitNormal()
                ? $totals->total_debit - $totals->total_credit
                : $totals->total_credit - $totals->total_debit;

            return [
                'account_id'      => $account->id,
                'account_code'    => $account->code,
                'account_name'    => $account->name,
                'account_type'    => $account->account_type,
                'opening_balance' => $openingBalance,
                'total_debit'     => (float) $totals->total_debit,
                'total_credit'    => (float) $totals->total_credit,
                'movement'        => $movementBalance,
                'closing_balance' => $openingBalance + $movementBalance,
            ];
        });
    }

    /**
     * Get trial balance for organization.
     *
     * Uses two DB aggregation queries (accounts + journal movement) instead of
     * one query per account — avoids N+1 on large charts of accounts.
     */
    public function getTrialBalance(
        int $organizationId,
        int $fiscalYearId,
        ?string $asOfDate = null
    ): array {
        $cacheKey = "trial_balance:fy{$fiscalYearId}:d{$asOfDate}";

        return $this->cache->rememberTransact($organizationId, $cacheKey, function () use ($organizationId, $fiscalYearId, $asOfDate): array {
            return $this->computeTrialBalance($organizationId, $fiscalYearId, $asOfDate);
        });
    }

    /**
     * Internal implementation of trial balance computation (not cached itself).
     */
    private function computeTrialBalance(
        int $organizationId,
        int $fiscalYearId,
        ?string $asOfDate
    ): array {
        // Single aggregate: posted movements per account for the fiscal year.
        $movementQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.organization_id', $organizationId)
            ->where('je.status', 'posted')
            ->where('je.fiscal_year_id', $fiscalYearId)
            ->select([
                'jel.account_id',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->groupBy('jel.account_id');

        if ($asOfDate) {
            $movementQuery->whereDate('je.entry_date', '<=', $asOfDate);
        }

        $movements = $movementQuery->get()->keyBy('account_id');

        // Opening balances for the fiscal year in one query.
        $openings = DB::table('account_opening_balances')
            ->where('fiscal_year_id', $fiscalYearId)
            ->select(['account_id', 'debit', 'credit'])
            ->get()
            ->keyBy('account_id');

        // Debit-normal types.
        $debitNormalTypes = [Account::TYPE_ASSET, Account::TYPE_EXPENSE];

        $trialBalance = [];
        $totalDebit   = 0;
        $totalCredit  = 0;

        Account::where('organization_id', $organizationId)
            ->where('is_header', false)
            ->where('is_active', true)
            ->orderBy('code')
            ->select(['id', 'code', 'name', 'account_type'])
            ->chunkById(200, function ($accounts) use ($movements, $openings, $debitNormalTypes, &$trialBalance, &$totalDebit, &$totalCredit): void {
                foreach ($accounts as $account) {
                    $mov     = $movements[$account->id] ?? null;
                    $opening = $openings[$account->id] ?? null;
                    $isDebitNormal = in_array($account->account_type, $debitNormalTypes, true);

                    $openingBalance = 0;
                    if ($opening) {
                        $openingBalance = $isDebitNormal
                            ? (float) $opening->debit - (float) $opening->credit
                            : (float) $opening->credit - (float) $opening->debit;
                    }

                    $movementDebit  = (float) ($mov->total_debit ?? 0);
                    $movementCredit = (float) ($mov->total_credit ?? 0);
                    $movement       = $isDebitNormal
                        ? $movementDebit - $movementCredit
                        : $movementCredit - $movementDebit;

                    $closingBalance = $openingBalance + $movement;

                    if ($closingBalance == 0) {
                        return;
                    }

                    $trialBalance[] = [
                        'account_code' => $account->code,
                        'account_name' => $account->name,
                        'account_type' => $account->account_type,
                        'debit'  => $closingBalance > 0 && $isDebitNormal
                            ? $closingBalance
                            : ($closingBalance < 0 && !$isDebitNormal ? abs($closingBalance) : 0),
                        'credit' => $closingBalance > 0 && !$isDebitNormal
                            ? $closingBalance
                            : ($closingBalance < 0 && $isDebitNormal ? abs($closingBalance) : 0),
                    ];

                    if ($isDebitNormal) {
                        $closingBalance > 0
                            ? ($totalDebit += $closingBalance)
                            : ($totalCredit += abs($closingBalance));
                    } else {
                        $closingBalance > 0
                            ? ($totalCredit += $closingBalance)
                            : ($totalDebit += abs($closingBalance));
                    }
                }
            });

        return [
            'as_of_date'     => $asOfDate ?? now()->toDateString(),
            'fiscal_year_id' => $fiscalYearId,
            'accounts'       => $trialBalance,
            'total_debit'    => $totalDebit,
            'total_credit'   => $totalCredit,
            'is_balanced'    => bccomp((string) $totalDebit, (string) $totalCredit, 4) === 0,
        ];
    }

    /**
     * Get balance sheet summary.
     *
     * Uses a single DB aggregate (one query) instead of N+1 per account.
     */
    public function getBalanceSheetSummary(
        int $organizationId,
        int $fiscalYearId,
        ?string $asOfDate = null
    ): array {
        $cacheKey = "balance_sheet:fy{$fiscalYearId}:d{$asOfDate}";

        return $this->cache->rememberTransact($organizationId, $cacheKey, function () use ($organizationId, $fiscalYearId, $asOfDate): array {
            return $this->computeBalanceSheet($organizationId, $fiscalYearId, $asOfDate);
        });
    }

    /**
     * Internal implementation of balance sheet computation (not cached itself).
     */
    private function computeBalanceSheet(
        int $organizationId,
        int $fiscalYearId,
        ?string $asOfDate
    ): array {
        $bsTypes = [Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY];

        // DB aggregate: net movement per account type for all BS accounts in one query.
        $movQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $organizationId)
            ->where('je.status', 'posted')
            ->where('je.fiscal_year_id', $fiscalYearId)
            ->where('a.is_header', false)
            ->where('a.is_active', true)
            ->whereIn('a.account_type', $bsTypes)
            ->select([
                'a.account_type',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->groupBy('a.account_type');

        if ($asOfDate) {
            $movQuery->whereDate('je.entry_date', '<=', $asOfDate);
        }

        $movements = $movQuery->get()->keyBy('account_type');

        // Opening balances grouped by account type in one query.
        $openings = DB::table('account_opening_balances as ob')
            ->join('chart_of_accounts as a', 'a.id', '=', 'ob.account_id')
            ->where('ob.fiscal_year_id', $fiscalYearId)
            ->where('a.is_header', false)
            ->where('a.is_active', true)
            ->whereIn('a.account_type', $bsTypes)
            ->select([
                'a.account_type',
                DB::raw('COALESCE(SUM(ob.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(ob.credit), 0) as total_credit'),
            ])
            ->groupBy('a.account_type')
            ->get()
            ->keyBy('account_type');

        $debitNormalTypes = [Account::TYPE_ASSET, Account::TYPE_EXPENSE];
        $summary          = [];

        foreach ($bsTypes as $type) {
            $mov     = $movements[$type] ?? null;
            $opening = $openings[$type] ?? null;
            $isDebitNormal = in_array($type, $debitNormalTypes, true);

            $openingBalance = 0;
            if ($opening) {
                $openingBalance = $isDebitNormal
                    ? (float) $opening->total_debit - (float) $opening->total_credit
                    : (float) $opening->total_credit - (float) $opening->total_debit;
            }

            $movement = $isDebitNormal
                ? (float) ($mov->total_debit ?? 0) - (float) ($mov->total_credit ?? 0)
                : (float) ($mov->total_credit ?? 0) - (float) ($mov->total_debit ?? 0);

            $summary[$type] = $openingBalance + $movement;
        }

        return [
            'as_of_date'        => $asOfDate ?? now()->toDateString(),
            'total_assets'      => $summary[Account::TYPE_ASSET]     ?? 0,
            'total_liabilities' => $summary[Account::TYPE_LIABILITY] ?? 0,
            'total_equity'      => $summary[Account::TYPE_EQUITY]    ?? 0,
            'is_balanced'       => bccomp(
                (string) ($summary[Account::TYPE_ASSET] ?? 0),
                (string) (($summary[Account::TYPE_LIABILITY] ?? 0) + ($summary[Account::TYPE_EQUITY] ?? 0)),
                4
            ) === 0,
        ];
    }

    /**
     * Get income statement (profit & loss) summary.
     */
    public function getIncomeStatementSummary(
        int $organizationId,
        int $fiscalYearId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        // Resolve date bounds before caching so the cache key is fully deterministic.
        $fiscalYear       = FiscalYear::findOrFail($fiscalYearId);
        $resolvedStart    = $startDate ?? $fiscalYear->start_date->toDateString();
        $resolvedEnd      = $endDate   ?? ($fiscalYear->is_closed ? $fiscalYear->end_date->toDateString() : now()->toDateString());

        $cacheKey = "income_statement:fy{$fiscalYearId}:s{$resolvedStart}:e{$resolvedEnd}";

        return $this->cache->rememberTransact($organizationId, $cacheKey, function () use ($organizationId, $fiscalYearId, $resolvedStart, $resolvedEnd): array {
            return $this->computeIncomeStatement($organizationId, $fiscalYearId, $resolvedStart, $resolvedEnd);
        });
    }

    /**
     * Internal implementation of income statement computation (not cached itself).
     */
    private function computeIncomeStatement(
        int $organizationId,
        int $fiscalYearId,
        string $startDate,
        string $endDate
    ): array {
        // Single DB aggregate for income and expense movements — no N+1.
        $plTypes = [Account::TYPE_INCOME, Account::TYPE_EXPENSE];

        $movQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $organizationId)
            ->where('je.status', 'posted')
            ->where('je.fiscal_year_id', $fiscalYearId)
            ->where('a.is_header', false)
            ->where('a.is_active', true)
            ->whereIn('a.account_type', $plTypes)
            ->whereDate('je.entry_date', '>=', $startDate)
            ->whereDate('je.entry_date', '<=', $endDate)
            ->select([
                'a.account_type',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->groupBy('a.account_type')
            ->get()
            ->keyBy('account_type');

        // Income is credit-normal: net = credit - debit
        $incomeMov  = $movQuery[Account::TYPE_INCOME]  ?? null;
        $expenseMov = $movQuery[Account::TYPE_EXPENSE] ?? null;

        $totalIncome   = (float) ($incomeMov->total_credit  ?? 0) - (float) ($incomeMov->total_debit   ?? 0);
        $totalExpenses = (float) ($expenseMov->total_debit  ?? 0) - (float) ($expenseMov->total_credit ?? 0);

        return [
            'period_start'   => $startDate,
            'period_end'     => $endDate,
            'total_income'   => abs($totalIncome),   // Income should be positive
            'total_expenses' => abs($totalExpenses), // Expenses should be positive
            'net_profit'     => abs($totalIncome) - abs($totalExpenses),
        ];
    }

    /**
     * Get account ledger (all transactions for an account).
     */
    public function getAccountLedger(
        int $accountId,
        ?int $fiscalYearId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $account = Account::findOrFail($accountId);

        $query = JournalEntryLine::with(['journalEntry'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId, $startDate, $endDate) {
                $q->where('status', 'posted');

                if ($fiscalYearId) {
                    $q->where('fiscal_year_id', $fiscalYearId);
                }

                if ($startDate) {
                    $q->whereDate('entry_date', '>=', $startDate);
                }

                if ($endDate) {
                    $q->whereDate('entry_date', '<=', $endDate);
                }
            })
            ->orderBy(
                JournalEntryLine::query()
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
                    ->select('entry_date')
            );

        $total = $query->count();
        $lines = $query->skip($offset)->take($limit)->get();

        $runningBalance = 0;
        $ledger = [];

        foreach ($lines as $line) {
            $entry = $line->journalEntry;

            if ($account->isDebitNormal()) {
                $runningBalance += ($line->base_debit - $line->base_credit);
            } else {
                $runningBalance += ($line->base_credit - $line->base_debit);
            }

            $ledger[] = [
                'date' => $entry->entry_date->toDateString(),
                'entry_number' => $entry->entry_number,
                'reference' => $entry->reference,
                'description' => $line->description ?? $entry->description,
                'debit' => (float) $line->base_debit,
                'credit' => (float) $line->base_credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->account_type,
            ],
            'transactions' => $ledger,
            'total_count' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
