<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Accounting\Account;
use App\Models\Budget\Budget;
use App\Models\Sales\Invoice;
use App\Models\Purchase\Bill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Get Profit & Loss statement.
     *
     * Single DB aggregate query (one JOIN + GROUP BY) instead of N+1 per account.
     */
    public function getProfitAndLoss(Carbon $startDate, Carbon $endDate): array
    {
        $orgId = auth()->user()->organization_id;

        // One query: sum debits/credits per account for income + expense accounts.
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereIn('a.account_type', ['income', 'expense'])
            ->where('a.is_header', false)
            ->where('a.is_active', true)
            ->whereBetween('je.entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select([
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.account_type',
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
            ])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type')
            ->get();

        $totalIncome = 0;
        $incomeBreakdown = [];
        $totalExpenses = 0;
        $expenseBreakdown = [];

        foreach ($rows as $row) {
            if ($row->account_type === 'income') {
                $balance = bcsub((string) $row->total_credit, (string) $row->total_debit, 4);
                if (bccomp($balance, '0', 4) !== 0) {
                    $incomeBreakdown[] = [
                        'account_id'   => $row->account_id,
                        'account_code' => $row->account_code,
                        'account_name' => $row->account_name,
                        'amount'       => (float) $balance,
                    ];
                    $totalIncome = bcadd((string) $totalIncome, (string) $balance, 4);
                }
            } else {
                $balance = bcsub((string) $row->total_debit, (string) $row->total_credit, 4);
                if (bccomp($balance, '0', 4) !== 0) {
                    $expenseBreakdown[] = [
                        'account_id'   => $row->account_id,
                        'account_code' => $row->account_code,
                        'account_name' => $row->account_name,
                        'amount'       => (float) $balance,
                    ];
                    $totalExpenses = bcadd((string) $totalExpenses, (string) $balance, 4);
                }
            }
        }

        $netProfit = bcsub((string) $totalIncome, (string) $totalExpenses, 4);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'income' => [
                'total' => (float) $totalIncome,
                'breakdown' => $incomeBreakdown,
            ],
            'expenses' => [
                'total' => (float) $totalExpenses,
                'breakdown' => $expenseBreakdown,
            ],
            'net_profit' => (float) $netProfit,
            'profit_margin' => bccomp((string) $totalIncome, '0', 4) > 0
                ? (float) bcmul(bcdiv((string) $netProfit, (string) $totalIncome, 8), '100', 4)
                : 0,
        ];
    }

    /**
     * Get Balance Sheet.
     *
     * Single DB aggregate query (one JOIN + GROUP BY) instead of N+1 per account.
     */
    public function getBalanceSheet(Carbon $asOfDate): array
    {
        $orgId = auth()->user()->organization_id;

        $bsTypes = ['asset', 'liability', 'equity'];

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereIn('a.account_type', $bsTypes)
            ->where('a.is_header', false)
            ->where('a.is_active', true)
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->select([
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.account_type',
                'a.sub_type',
                DB::raw('COALESCE(SUM(jel.base_debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.base_credit), 0) as total_credit'),
            ])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.account_type', 'a.sub_type')
            ->get();

        $assets = ['current' => [], 'fixed' => [], 'other' => []];
        $liabilities = ['current' => [], 'long_term' => []];
        $equity = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($rows as $row) {
            $balance = match ($row->account_type) {
                'asset'                  => bcsub((string) $row->total_debit, (string) $row->total_credit, 4),
                'liability', 'equity'    => bcsub((string) $row->total_credit, (string) $row->total_debit, 4),
                default                  => '0',
            };

            if (bccomp($balance, '0', 4) === 0) {
                continue;
            }

            $item = [
                'account_id'   => $row->account_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'amount'       => (float) $balance,
            ];

            switch ($row->account_type) {
                case 'asset':
                    if (in_array($row->sub_type, ['bank', 'cash', 'receivable', 'inventory'])) {
                        $assets['current'][] = $item;
                    } elseif ($row->sub_type === 'fixed_asset') {
                        $assets['fixed'][] = $item;
                    } else {
                        $assets['other'][] = $item;
                    }
                    $totalAssets = bcadd((string) $totalAssets, (string) $balance, 4);
                    break;

                case 'liability':
                    if (in_array($row->sub_type, ['payable', 'current_liability'])) {
                        $liabilities['current'][] = $item;
                    } else {
                        $liabilities['long_term'][] = $item;
                    }
                    $totalLiabilities = bcadd((string) $totalLiabilities, (string) $balance, 4);
                    break;

                case 'equity':
                    $equity[] = $item;
                    $totalEquity = bcadd((string) $totalEquity, (string) $balance, 4);
                    break;
            }
        }

        // Add current period net income to retained earnings
        $currentYearStart = $asOfDate->copy()->startOfYear();
        $pnl = $this->getProfitAndLoss($currentYearStart, $asOfDate);
        $retainedEarnings = (float) $pnl['net_profit'];

        $equity[] = [
            'account_code' => 'RE',
            'account_name' => 'Current Year Earnings',
            'amount' => $retainedEarnings,
        ];
        $totalEquity = bcadd((string) $totalEquity, (string) $retainedEarnings, 4);

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'assets' => [
                'current_assets' => $assets['current'],
                'fixed_assets' => $assets['fixed'],
                'other_assets' => $assets['other'],
                'total' => (float) $totalAssets,
            ],
            'liabilities' => [
                'current_liabilities' => $liabilities['current'],
                'long_term_liabilities' => $liabilities['long_term'],
                'total' => (float) $totalLiabilities,
            ],
            'equity' => [
                'items' => $equity,
                'total' => (float) $totalEquity,
            ],
            'total_liabilities_and_equity' => (float) bcadd((string) $totalLiabilities, (string) $totalEquity, 4),
            'is_balanced' => bccomp((string) $totalAssets, bcadd((string) $totalLiabilities, (string) $totalEquity, 4), 2) === 0,
        ];
    }

    /**
     * Get Cash Flow statement.
     */
    public function getCashFlow(Carbon $startDate, Carbon $endDate): array
    {
        $orgId = auth()->user()->organization_id;

        // Get bank/cash account IDs
        $cashAccountIds = Account::where('organization_id', $orgId)
            ->where('type', 'asset')
            ->whereIn('sub_type', ['bank', 'cash'])
            ->pluck('id');

        // Join journal_entries directly — avoids N+1 from eager-loading.
        $cashMovements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereBetween('je.entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select([
                'je.entry_date',
                'je.reference',
                'je.source_type',
                'je.description as entry_description',
                'jel.description as line_description',
                'jel.base_debit as debit',
                'jel.base_credit as credit',
            ])
            ->orderBy('je.entry_date')
            ->limit(2000)
            ->get();

        $operatingCashFlow = 0;
        $investingCashFlow = 0;
        $financingCashFlow = 0;

        $operatingActivities = [];
        $investingActivities = [];
        $financingActivities = [];

        foreach ($cashMovements as $line) {
            $cashChange = bcsub((string) $line->debit, (string) $line->credit, 4);
            $sourceType = $line->source_type ?? '';

            $activity = [
                'date'        => $line->entry_date,
                'reference'   => $line->reference,
                'description' => $line->line_description ?? $line->entry_description,
                'amount'      => (float) $cashChange,
            ];

            // Classify based on source type
            if (str_contains($sourceType, 'Invoice') || str_contains($sourceType, 'Bill') || str_contains($sourceType, 'Payment')) {
                $operatingActivities[] = $activity;
                $operatingCashFlow = bcadd((string) $operatingCashFlow, (string) $cashChange, 4);
            } elseif (str_contains($sourceType, 'Asset') || str_contains($sourceType, 'Depreciation')) {
                $investingActivities[] = $activity;
                $investingCashFlow = bcadd((string) $investingCashFlow, (string) $cashChange, 4);
            } else {
                $financingActivities[] = $activity;
                $financingCashFlow = bcadd((string) $financingCashFlow, (string) $cashChange, 4);
            }
        }

        // Get opening balance via single aggregate — no N+1.
        $openingBalance = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<', $startDate)
            ->selectRaw('COALESCE(SUM(jel.base_debit), 0) - COALESCE(SUM(jel.base_credit), 0) as balance')
            ->first()
            ->balance ?? 0;

        $netCashChange = bcadd(
            bcadd((string) $operatingCashFlow, (string) $investingCashFlow, 4),
            (string) $financingCashFlow,
            4
        );

        $closingBalance = bcadd((string) $openingBalance, (string) $netCashChange, 4);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'opening_balance' => (float) $openingBalance,
            'operating_activities' => [
                'items' => $operatingActivities,
                'total' => (float) $operatingCashFlow,
            ],
            'investing_activities' => [
                'items' => $investingActivities,
                'total' => (float) $investingCashFlow,
            ],
            'financing_activities' => [
                'items' => $financingActivities,
                'total' => (float) $financingCashFlow,
            ],
            'net_cash_change' => (float) $netCashChange,
            'closing_balance' => (float) $closingBalance,
        ];
    }

    /**
     * Get Accounts Receivable Aging report.
     */
    public function getReceivableAging(): array
    {
        $today = now();
        $orgId = auth()->user()->organization_id;

        $agingExpr = "CASE
            WHEN DATEDIFF(CURDATE(), due_date) <= 0  THEN 'current'
            WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1_30'
            WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31_60'
            WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61_90'
            ELSE 'over_90'
        END";

        $baseQuery = Invoice::where('organization_id', $orgId)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('amount_due', '>', 0);

        // Summary via single GROUP BY query
        $bucketRows = (clone $baseQuery)
            ->selectRaw("{$agingExpr} as bucket, SUM(amount_due) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        // Detail: top 200 ordered by most overdue — no full table scan
        $details = (clone $baseQuery)
            ->join('contacts as ar_cust', 'ar_cust.id', '=', 'invoices.customer_id', 'left')
            ->selectRaw("
                invoices.id as invoice_id, invoices.invoice_number,
                invoices.customer_id, invoices.customer_name,
                COALESCE(ar_cust.company_name, invoices.customer_name) as customer_display,
                invoices.invoice_date, invoices.due_date,
                invoices.total, invoices.amount_due,
                GREATEST(0, DATEDIFF(CURDATE(), invoices.due_date)) as days_overdue,
                {$agingExpr} as aging_bucket
            ")
            ->orderByRaw('days_overdue DESC')
            ->limit(200)
            ->get()->toArray();

        return [
            'as_of_date' => $today->format('Y-m-d'),
            'summary' => [
                'current'      => (float) ($bucketRows['current']  ?? 0),
                '1_30_days'    => (float) ($bucketRows['1_30']      ?? 0),
                '31_60_days'   => (float) ($bucketRows['31_60']     ?? 0),
                '61_90_days'   => (float) ($bucketRows['61_90']     ?? 0),
                'over_90_days' => (float) ($bucketRows['over_90']   ?? 0),
                'total'        => (float) $bucketRows->sum(),
            ],
            'details' => $details,
        ];
    }

    /**
     * Get Accounts Payable Aging report.
     */
    public function getPayableAging(): array
    {
        $today = now();
        $orgId = auth()->user()->organization_id;

        $apAgingExpr = "CASE
            WHEN DATEDIFF(CURDATE(), due_date) <= 0  THEN 'current'
            WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1_30'
            WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31_60'
            WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61_90'
            ELSE 'over_90'
        END";

        $apBase = Bill::where('organization_id', $orgId)
            ->whereIn('status', ['approved', 'partial', 'overdue'])
            ->where('amount_due', '>', 0);

        // Summary via single GROUP BY query
        $apBuckets = (clone $apBase)
            ->selectRaw("{$apAgingExpr} as bucket, SUM(amount_due) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        // Detail: top 200 ordered by most overdue
        $apDetails = (clone $apBase)
            ->join('contacts as ap_sup', 'ap_sup.id', '=', 'bills.supplier_id', 'left')
            ->selectRaw("
                bills.id as bill_id, bills.bill_number,
                bills.supplier_id, bills.supplier_name,
                COALESCE(ap_sup.company_name, bills.supplier_name) as supplier_display,
                bills.bill_date, bills.due_date,
                bills.total, bills.amount_due,
                GREATEST(0, DATEDIFF(CURDATE(), bills.due_date)) as days_overdue,
                {$apAgingExpr} as aging_bucket
            ")
            ->orderByRaw('days_overdue DESC')
            ->limit(200)
            ->get()->toArray();

        return [
            'as_of_date' => $today->format('Y-m-d'),
            'summary' => [
                'current'      => (float) ($apBuckets['current']  ?? 0),
                '1_30_days'    => (float) ($apBuckets['1_30']      ?? 0),
                '31_60_days'   => (float) ($apBuckets['31_60']     ?? 0),
                '61_90_days'   => (float) ($apBuckets['61_90']     ?? 0),
                'over_90_days' => (float) ($apBuckets['over_90']   ?? 0),
                'total'        => (float) $apBuckets->sum(),
            ],
            'details' => $apDetails,
        ];
    }

    /**
     * Get aging bucket for days overdue.
     */
    protected function getAgingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => 'over_90',
        };
    }

    /**
     * Get Trial Balance.
     *
     * Single aggregate query (GROUP BY account) instead of loading all accounts
     * and their journal lines into PHP memory.
     */
    public function getTrialBalance(Carbon $asOfDate): array
    {
        $orgId = auth()->user()->organization_id;

        // One query: sum debits/credits per account up to $asOfDate.
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->join('chart_of_accounts as coa', 'jel.account_id', '=', 'coa.id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<=', $asOfDate)
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.account_type', 'coa.sub_type')
            ->orderBy('coa.code')
            ->select([
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.account_type',
                DB::raw('COALESCE(SUM(jel.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(jel.credit), 0) as total_credit'),
            ])
            ->get();

        $lines = [];
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($rows as $row) {
            $debit  = (string) $row->total_debit;
            $credit = (string) $row->total_credit;

            if (bccomp($debit, '0', 4) === 0 && bccomp($credit, '0', 4) === 0) {
                continue;
            }

            // Balance direction follows account normal balance convention.
            $balance = match ($row->account_type) {
                'asset', 'expense' => bcsub($debit, $credit, 4),
                default            => bcsub($credit, $debit, 4),
            };

            $balanceDebit  = bccomp($balance, '0', 4) > 0 ? (float) $balance : 0.0;
            $balanceCredit = bccomp($balance, '0', 4) < 0 ? (float) bcsub('0', $balance, 4) : 0.0;

            $lines[] = [
                'account_id'   => $row->account_id,
                'account_code' => $row->code,
                'account_name' => $row->name,
                'account_type' => $row->account_type,
                'debit'        => $balanceDebit,
                'credit'       => $balanceCredit,
            ];

            $totalDebit  = bcadd($totalDebit, (string) $balanceDebit, 4);
            $totalCredit = bcadd($totalCredit, (string) $balanceCredit, 4);
        }

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'lines'      => $lines,
            'totals'     => [
                'debit'  => (float) $totalDebit,
                'credit' => (float) $totalCredit,
            ],
            'is_balanced' => bccomp($totalDebit, $totalCredit, 2) === 0,
        ];
    }

    /**
     * Actual vs Budget variance report.
     *
     * Returns all active budgets for the organisation with per-line variance
     * (budgeted, committed, actual, variance_amount, variance_pct, is_over_budget).
     *
     * Optional filters:
     *   budget_type: annual|quarterly|project|department
     *   period_start / period_end: ISO date strings for budget period overlap
     *
     * @param array{budget_type?: string, period_start?: string, period_end?: string} $filters
     */
    public function getActualVsBudget(array $filters = []): array
    {
        $orgId = auth()->user()->organization_id;

        $query = Budget::where('organization_id', $orgId)
            ->whereIn('status', [Budget::STATUS_ACTIVE, Budget::STATUS_APPROVED, Budget::STATUS_CLOSED])
            ->with(['lines.account', 'fiscalYear']);

        if (! empty($filters['budget_type'])) {
            $query->where('budget_type', $filters['budget_type']);
        }

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $query->forPeriod($filters['period_start'], $filters['period_end']);
        }

        $result = [];

        // chunkById processes 50 budgets at a time — avoids loading all lines into memory at once
        $query->chunkById(50, function ($budgets) use (&$result) {
            foreach ($budgets as $budget) {
                $totalBudgeted  = '0.00';
                $totalCommitted = '0.00';
                $totalActual    = '0.00';

                $lines = $budget->lines->map(function ($line) use (&$totalBudgeted, &$totalCommitted, &$totalActual) {
                    $budgeted    = (string) $line->total_amount;
                    $committed   = (string) $line->committed_amount;
                    $actual      = (string) $line->actual_amount;
                    $variance    = bcsub($budgeted, $actual, 2);
                    $variancePct = bccomp($budgeted, '0', 2) > 0
                        ? round((float) bcdiv($variance, $budgeted, 6) * 100, 2)
                        : 0.0;

                    $totalBudgeted  = bcadd($totalBudgeted, $budgeted, 2);
                    $totalCommitted = bcadd($totalCommitted, $committed, 2);
                    $totalActual    = bcadd($totalActual, $actual, 2);

                    return [
                        'account_code'    => $line->account?->code,
                        'account_name'    => $line->account?->name,
                        'budget_amount'   => (float) $budgeted,
                        'committed'       => (float) $committed,
                        'actual'          => (float) $actual,
                        'available'       => (float) bcsub(bcsub($budgeted, $committed, 2), $actual, 2),
                        'variance_amount' => (float) $variance,
                        'variance_pct'    => $variancePct,
                        'is_over_budget'  => bccomp($actual, $budgeted, 2) > 0,
                    ];
                });

                $totalVariance    = bcsub($totalBudgeted, $totalActual, 2);
                $totalVariancePct = bccomp($totalBudgeted, '0', 2) > 0
                    ? round((float) bcdiv($totalVariance, $totalBudgeted, 6) * 100, 2)
                    : 0.0;

                $result[] = [
                    'budget_id'          => $budget->id,
                    'budget_uuid'        => $budget->uuid,
                    'name'               => $budget->name,
                    'budget_type'        => $budget->budget_type,
                    'status'             => $budget->status,
                    'period_start'       => $budget->period_start?->format('Y-m-d'),
                    'period_end'         => $budget->period_end?->format('Y-m-d'),
                    'fiscal_year'        => $budget->fiscalYear?->name,
                    'total_budgeted'     => (float) $totalBudgeted,
                    'total_committed'    => (float) $totalCommitted,
                    'total_actual'       => (float) $totalActual,
                    'total_variance'     => (float) $totalVariance,
                    'total_variance_pct' => $totalVariancePct,
                    'utilization_pct'    => $budget->getUtilizationPercent(),
                    'lines'              => $lines,
                ];
            }
        });

        // Sort descending by period_start after chunking (chunkById uses id ordering internally)
        usort($result, fn ($a, $b) => strcmp((string) $b['period_start'], (string) $a['period_start']));

        return [
            'generated_at' => now()->toIso8601String(),
            'filters'      => $filters,
            'budgets'      => $result,
        ];
    }
}
