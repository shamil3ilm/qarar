<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\ProfitCenter;
use App\Models\Accounting\ProfitCenterPlan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProfitCenterService
{
    // ----------------------------------------------------------------
    // Profit Center CRUD
    // ----------------------------------------------------------------

    public function createProfitCenter(array $data, int $userId): ProfitCenter
    {
        return DB::transaction(function () use ($data, $userId): ProfitCenter {
            $this->validateUniqueCode(
                $data['organization_id'],
                $data['code']
            );

            return ProfitCenter::create($data);
        });
    }

    public function updateProfitCenter(ProfitCenter $profitCenter, array $data, int $userId): ProfitCenter
    {
        return DB::transaction(function () use ($profitCenter, $data, $userId): ProfitCenter {
            if (isset($data['code']) && $data['code'] !== $profitCenter->code) {
                $this->validateUniqueCode(
                    $profitCenter->organization_id,
                    $data['code'],
                    $profitCenter->id
                );
            }

            $profitCenter->update($data);

            return $profitCenter->fresh();
        });
    }

    public function deactivate(ProfitCenter $profitCenter, int $userId): ProfitCenter
    {
        return DB::transaction(function () use ($profitCenter): ProfitCenter {
            $profitCenter->update(['status' => ProfitCenter::STATUS_INACTIVE]);

            return $profitCenter->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Report revenues and expenses for one or all profit centers.
     *
     * Revenue accounts (income / revenue types): credit-side is positive.
     * Expense accounts: debit-side is positive.
     *
     * Profit centers are linked to journal lines via cost_center_assignments
     * that carry a profit_center_id.
     *
     * @return array<int, array{profit_center_id: int, code: string, name: string, revenue: float, expense: float, profit: float}>
     */
    public function getProfitCenterReport(
        int $orgId,
        string $from,
        string $to,
        ?int $profitCenterId = null
    ): array {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('cost_center_assignments as cca', function ($join): void {
                $join->on('cca.cost_center_id', '=', 'jel.cost_center_id')
                    ->whereNotNull('cca.profit_center_id');
            })
            ->join('profit_centers as pc', 'pc.id', '=', 'cca.profit_center_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', JournalEntry::STATUS_POSTED)
            ->whereDate('je.entry_date', '>=', $from)
            ->whereDate('je.entry_date', '<=', $to)
            ->whereNull('pc.deleted_at')
            ->select(
                'pc.id as profit_center_id',
                'pc.code',
                'pc.name',
                'a.account_type',
                DB::raw('SUM(jel.debit)  AS total_debit'),
                DB::raw('SUM(jel.credit) AS total_credit')
            )
            ->groupBy('pc.id', 'pc.code', 'pc.name', 'a.account_type');

        if ($profitCenterId !== null) {
            $query->where('pc.id', $profitCenterId);
        }

        $rows = $query->get();

        // Aggregate by profit center
        $byPc = [];

        foreach ($rows as $row) {
            $pcId = $row->profit_center_id;

            if (!isset($byPc[$pcId])) {
                $byPc[$pcId] = [
                    'profit_center_id' => $pcId,
                    'code'             => $row->code,
                    'name'             => $row->name,
                    'revenue'          => '0',
                    'expense'          => '0',
                ];
            }

            $accountType = strtolower((string) $row->account_type);

            if (in_array($accountType, ['income', 'revenue'], true)) {
                // Revenue = net credit on income accounts
                $byPc[$pcId]['revenue'] = bcadd((string)$byPc[$pcId]['revenue'], bcsub((string)(float)$row->total_credit, (string)(float)$row->total_debit, 4), 4);
            } else {
                // Expense = net debit on non-income accounts
                $byPc[$pcId]['expense'] = bcadd((string)$byPc[$pcId]['expense'], bcsub((string)(float)$row->total_debit, (string)(float)$row->total_credit, 4), 4);
            }
        }

        return array_map(function (array $pc): array {
            $pc['profit'] = bcsub((string)$pc['revenue'], (string)$pc['expense'], 4);

            return $pc;
        }, array_values($byPc));
    }

    // ----------------------------------------------------------------
    // Period Planning
    // ----------------------------------------------------------------

    /**
     * Upsert a period-level plan for a profit center.
     *
     * plan_profit is computed as plan_revenue - plan_cost and stored.
     */
    public function setPlan(
        ProfitCenter $profitCenter,
        int $fiscalYear,
        int $period,
        float $revenue,
        float $cost
    ): ProfitCenterPlan {
        return DB::transaction(function () use ($profitCenter, $fiscalYear, $period, $revenue, $cost): ProfitCenterPlan {
            if ($period < 1 || $period > 12) {
                throw new InvalidArgumentException('Period must be between 1 and 12.');
            }

            if ($revenue < 0 || $cost < 0) {
                throw new InvalidArgumentException('Plan revenue and cost cannot be negative.');
            }

            $profit = $revenue - $cost;

            /** @var ProfitCenterPlan $plan */
            $plan = ProfitCenterPlan::updateOrCreate(
                [
                    'organization_id'  => $profitCenter->organization_id,
                    'profit_center_id' => $profitCenter->id,
                    'fiscal_year'      => $fiscalYear,
                    'period'           => $period,
                ],
                [
                    'plan_revenue'  => $revenue,
                    'plan_cost'     => $cost,
                    'plan_profit'   => $profit,
                    'currency_code' => 'SAR',
                    'created_by'    => auth()->id(),
                ]
            );

            return $plan->fresh(['profitCenter:id,code,name']);
        });
    }

    /**
     * Return all 12 periods' plan for a profit center / fiscal year.
     *
     * @return array{fiscal_year: int, periods: array<int, array{period: int, plan_revenue: float, plan_cost: float, plan_profit: float}>}
     */
    public function getPlan(ProfitCenter $profitCenter, int $fiscalYear): array
    {
        $rows = ProfitCenterPlan::where('profit_center_id', $profitCenter->id)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $periods = [];
        for ($p = 1; $p <= 12; $p++) {
            $row = $rows->get($p);
            $periods[$p] = [
                'period'       => $p,
                'plan_revenue' => $row !== null ? (float) $row->plan_revenue : 0.0,
                'plan_cost'    => $row !== null ? (float) $row->plan_cost    : 0.0,
                'plan_profit'  => $row !== null ? (float) $row->plan_profit  : 0.0,
            ];
        }

        return [
            'fiscal_year' => $fiscalYear,
            'periods'     => $periods,
        ];
    }

    /**
     * Plan vs actual for a profit center across all 12 periods of a fiscal year.
     *
     * @return array{fiscal_year: int, periods: list<array{period: int, plan_revenue: float, plan_cost: float, plan_profit: float, actual_revenue: float, actual_cost: float, actual_profit: float, revenue_variance: float, cost_variance: float, profit_variance: float, variance_pct: float|null}>}
     */
    public function getPlanVsActual(ProfitCenter $profitCenter, int $fiscalYear): array
    {
        // Load plans
        $plans = ProfitCenterPlan::where('profit_center_id', $profitCenter->id)
            ->where('fiscal_year', $fiscalYear)
            ->get()
            ->keyBy('period');

        // Load actuals from journal entry lines for each month of the year
        $actuals = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('cost_center_assignments as cca', function ($join): void {
                $join->on('cca.cost_center_id', '=', 'jel.cost_center_id')
                    ->whereNotNull('cca.profit_center_id');
            })
            ->join('profit_centers as pc', 'pc.id', '=', 'cca.profit_center_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('pc.id', $profitCenter->id)
            ->where('je.organization_id', $profitCenter->organization_id)
            ->where('je.status', JournalEntry::STATUS_POSTED)
            ->whereYear('je.entry_date', $fiscalYear)
            ->select(
                DB::raw('MONTH(je.entry_date) AS period'),
                'a.account_type',
                DB::raw('SUM(jel.debit)  AS total_debit'),
                DB::raw('SUM(jel.credit) AS total_credit')
            )
            ->groupBy(DB::raw('MONTH(je.entry_date)'), 'a.account_type')
            ->get();

        // Aggregate actuals by period
        $actualByPeriod = [];
        foreach ($actuals as $row) {
            $p = (int) $row->period;
            if (!isset($actualByPeriod[$p])) {
                $actualByPeriod[$p] = ['revenue' => 0.0, 'cost' => 0.0];
            }

            $accountType = strtolower((string) $row->account_type);
            if (in_array($accountType, ['income', 'revenue'], true)) {
                $actualByPeriod[$p]['revenue'] += (float) $row->total_credit - (float) $row->total_debit;
            } else {
                $actualByPeriod[$p]['cost'] += (float) $row->total_debit - (float) $row->total_credit;
            }
        }

        $periods = [];
        for ($p = 1; $p <= 12; $p++) {
            $plan        = $plans->get($p);
            $planRevenue = $plan !== null ? (float) $plan->plan_revenue : 0.0;
            $planCost    = $plan !== null ? (float) $plan->plan_cost    : 0.0;
            $planProfit  = $plan !== null ? (float) $plan->plan_profit  : 0.0;

            $actRevenue  = $actualByPeriod[$p]['revenue'] ?? 0.0;
            $actCost     = $actualByPeriod[$p]['cost']    ?? 0.0;
            $actProfit   = $actRevenue - $actCost;

            $revVariance    = $actRevenue - $planRevenue;
            $profitVariance = $actProfit  - $planProfit;

            $periods[] = [
                'period'           => $p,
                'plan_revenue'     => $planRevenue,
                'plan_cost'        => $planCost,
                'plan_profit'      => $planProfit,
                'actual_revenue'   => $actRevenue,
                'actual_cost'      => $actCost,
                'actual_profit'    => $actProfit,
                'revenue_variance' => $revVariance,
                'cost_variance'    => $actCost - $planCost,
                'profit_variance'  => $profitVariance,
                'variance_pct'     => $planRevenue != 0
                    ? round(($revVariance / $planRevenue) * 100, 2)
                    : null,
            ];
        }

        return [
            'fiscal_year' => $fiscalYear,
            'periods'     => $periods,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function validateUniqueCode(int $orgId, string $code, ?int $excludeId = null): void
    {
        $query = ProfitCenter::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "A profit center with code [{$code}] already exists in this organization."
            );
        }
    }
}
