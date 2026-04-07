<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\CostAllocation;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostCenterAssignment;
use App\Models\Accounting\CostCenterBudget;
use App\Models\Accounting\CostCenterBudgetLine;
use App\Models\Accounting\CostElement;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\HR\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CostCenterService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    // ----------------------------------------------------------------
    // Cost Center CRUD
    // ----------------------------------------------------------------

    public function createCostCenter(array $data, int $userId): CostCenter
    {
        return DB::transaction(function () use ($data, $userId): CostCenter {
            $this->validateUniqueCode(
                $data['organization_id'],
                $data['code']
            );

            return CostCenter::create($data);
        });
    }

    public function updateCostCenter(CostCenter $costCenter, array $data, int $userId): CostCenter
    {
        return DB::transaction(function () use ($costCenter, $data, $userId): CostCenter {
            // Validate uniqueness only if code is changing
            if (isset($data['code']) && $data['code'] !== $costCenter->code) {
                $this->validateUniqueCode(
                    $costCenter->organization_id,
                    $data['code'],
                    $costCenter->id
                );
            }

            $costCenter->update($data);

            return $costCenter->fresh();
        });
    }

    public function deactivate(CostCenter $costCenter, int $userId): CostCenter
    {
        return DB::transaction(function () use ($costCenter): CostCenter {
            $costCenter->update(['status' => CostCenter::STATUS_INACTIVE]);

            return $costCenter->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Assignment
    // ----------------------------------------------------------------

    /**
     * Assign an employee to a cost center (and optionally a profit center).
     */
    public function assignEmployee(
        Employee $employee,
        int $costCenterId,
        ?int $profitCenterId,
        float $splitPercent,
        string $effectiveFrom,
        int $userId
    ): CostCenterAssignment {
        return DB::transaction(function () use (
            $employee,
            $costCenterId,
            $profitCenterId,
            $splitPercent,
            $effectiveFrom,
            $userId
        ): CostCenterAssignment {
            if ($splitPercent <= 0 || $splitPercent > 100) {
                throw new InvalidArgumentException(
                    'split_percent must be between 0.01 and 100.'
                );
            }

            $costCenter = CostCenter::findOrFail($costCenterId);

            if (!$costCenter->isActive()) {
                throw new InvalidArgumentException(
                    "Cost center [{$costCenter->code}] is not active."
                );
            }

            return CostCenterAssignment::create([
                'organization_id'  => $employee->organization_id,
                'assignable_type'  => Employee::class,
                'assignable_id'    => $employee->id,
                'cost_center_id'   => $costCenterId,
                'profit_center_id' => $profitCenterId,
                'split_percent'    => $splitPercent,
                'effective_from'   => $effectiveFrom,
                'created_by'       => $userId,
            ]);
        });
    }

    // ----------------------------------------------------------------
    // Allocations
    // ----------------------------------------------------------------

    public function createAllocation(array $data, int $userId): CostAllocation
    {
        return DB::transaction(function () use ($data, $userId): CostAllocation {
            $from = CostCenter::findOrFail($data['from_cost_center_id']);
            $to   = CostCenter::findOrFail($data['to_cost_center_id']);

            if ($from->id === $to->id) {
                throw new InvalidArgumentException(
                    'Source and destination cost centers must be different.'
                );
            }

            if (!$from->isActive() || !$to->isActive()) {
                throw new InvalidArgumentException(
                    'Both cost centers must be active to create an allocation.'
                );
            }

            $this->validateAllocationAmounts($data);

            return CostAllocation::create(array_merge($data, [
                'status'     => CostAllocation::STATUS_DRAFT,
                'created_by' => $userId,
            ]));
        });
    }

    /**
     * Post a draft allocation by creating a balancing journal entry.
     * Cr: from_cost_center GL account  |  Dr: to_cost_center GL account
     */
    public function postAllocation(CostAllocation $allocation, int $userId): CostAllocation
    {
        return DB::transaction(function () use ($allocation, $userId): CostAllocation {
            if (!$allocation->isDraft()) {
                throw new InvalidArgumentException(
                    'Only draft allocations can be posted.'
                );
            }

            $amount = $this->resolveAllocationAmount($allocation);

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Resolved allocation amount must be greater than zero.'
                );
            }

            $fromCenter = $allocation->fromCostCenter()->with('glAccount')->firstOrFail();
            $toCenter   = $allocation->toCostCenter()->with('glAccount')->firstOrFail();

            if ($fromCenter->gl_account_id === null || $toCenter->gl_account_id === null) {
                throw new InvalidArgumentException(
                    'Both cost centers must have a GL account assigned before posting.'
                );
            }

            $journalEntry = $this->journalService->createEntry(
                [
                    'organization_id' => $allocation->organization_id,
                    'entry_date'      => $allocation->period_end->toDateString(),
                    'fiscal_year_id'  => $allocation->fiscal_year_id,
                    'description'     => $allocation->description
                        ?? "Cost allocation: {$fromCenter->code} → {$toCenter->code}",
                    'source_type'     => CostAllocation::class,
                    'source_id'       => $allocation->id,
                    'currency_code'   => 'SAR',
                    'exchange_rate'   => 1,
                    'created_by'      => $userId,
                ],
                [
                    [
                        'account_id'      => $fromCenter->gl_account_id,
                        'description'     => "Cost allocation credit — {$fromCenter->code}",
                        'debit'           => 0,
                        'credit'          => $amount,
                        'cost_center_id'  => $fromCenter->id,
                    ],
                    [
                        'account_id'      => $toCenter->gl_account_id,
                        'description'     => "Cost allocation debit — {$toCenter->code}",
                        'debit'           => $amount,
                        'credit'          => 0,
                        'cost_center_id'  => $toCenter->id,
                    ],
                ]
            );

            // Post the journal entry immediately
            $this->journalService->postEntry($journalEntry);

            $allocation->update([
                'status'           => CostAllocation::STATUS_POSTED,
                'journal_entry_id' => $journalEntry->id,
            ]);

            return $allocation->fresh(['fromCostCenter', 'toCostCenter', 'journalEntry']);
        });
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Aggregate journal entry lines by cost center for the given period.
     *
     * @return array<int, array{cost_center_id: int, code: string, name: string, total_debit: float, total_credit: float, net: float}>
     */
    public function getCostCenterReport(
        int $orgId,
        string $from,
        string $to,
        ?int $costCenterId = null
    ): array {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('cost_centers as cc', 'cc.id', '=', 'jel.cost_center_id')
            ->where('je.organization_id', $orgId)
            ->where('je.status', JournalEntry::STATUS_POSTED)
            ->whereDate('je.entry_date', '>=', $from)
            ->whereDate('je.entry_date', '<=', $to)
            ->whereNull('cc.deleted_at')
            ->select(
                'cc.id as cost_center_id',
                'cc.code',
                'cc.name',
                DB::raw('SUM(jel.debit)  AS total_debit'),
                DB::raw('SUM(jel.credit) AS total_credit'),
                DB::raw('SUM(jel.debit) - SUM(jel.credit) AS net')
            )
            ->groupBy('cc.id', 'cc.code', 'cc.name');

        if ($costCenterId !== null) {
            $query->where('jel.cost_center_id', $costCenterId);
        }

        return $query->orderBy('cc.code')->get()->map(function (object $row): array {
            return [
                'cost_center_id' => $row->cost_center_id,
                'code'           => $row->code,
                'name'           => $row->name,
                'total_debit'    => (float) $row->total_debit,
                'total_credit'   => (float) $row->total_credit,
                'net'            => (float) $row->net,
            ];
        })->toArray();
    }

    /**
     * Report revenues and expenses per profit center derived from cost center
     * assignments linked to journal lines.
     *
     * @return array<int, array{profit_center_id: int, code: string, name: string, revenue: float, expense: float, profit: float}>
     */
    public function getProfitCenterReport(int $orgId, string $from, string $to): array
    {
        // Join through cost_center_assignments to reach profit centers
        $rows = DB::table('journal_entry_lines as jel')
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
            ->groupBy('pc.id', 'pc.code', 'pc.name', 'a.account_type')
            ->get();

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

            // Revenue account types: income, revenue
            if (in_array(strtolower($row->account_type), ['income', 'revenue'], true)) {
                // Revenue = credits - debits on income accounts
                $byPc[$pcId]['revenue'] = bcadd((string)$byPc[$pcId]['revenue'], bcsub((string)(float)$row->total_credit, (string)(float)$row->total_debit, 4), 4);
            } else {
                // Expense = debits - credits on non-income accounts
                $byPc[$pcId]['expense'] = bcadd((string)$byPc[$pcId]['expense'], bcsub((string)(float)$row->total_debit, (string)(float)$row->total_credit, 4), 4);
            }
        }

        return array_map(function (array $pc): array {
            $pc['profit'] = bcsub((string)$pc['revenue'], (string)$pc['expense'], 4);

            return $pc;
        }, array_values($byPc));
    }

    // ----------------------------------------------------------------
    // Plan vs Actual Report
    // ----------------------------------------------------------------

    /**
     * Plan vs actual report for a cost center for a given fiscal year and optional period.
     *
     * Loads CostCenterBudget + CostCenterBudgetLine records for the planned amounts,
     * then queries JournalEntryLines for actual costs, merges by cost element.
     *
     * @param  int       $fiscalYear  Fiscal year integer (e.g. 2025)
     * @param  int|null  $period      Fiscal period 1–12, or null for full year
     * @return array{cost_center_id: int, fiscal_year: int, period: int|null, total_planned: float, total_actual: float, total_variance: float, utilization_pct: float|null, by_cost_element: list<array{cost_element_id: int|null, name: string, planned: float, actual: float, variance: float, variance_pct: float|null}>}
     */
    public function getCostCenterPlanVsActual(
        CostCenter $costCenter,
        int $fiscalYear,
        ?int $period = null
    ): array {
        // ------------------------------------------------------------------
        // 1. Load plan lines from CostCenterBudget
        // ------------------------------------------------------------------
        $budgetQuery = CostCenterBudget::where('cost_center_id', $costCenter->id)
            ->where('fiscal_year', $fiscalYear);

        $budgets = $budgetQuery->with('lines.costElement')->get();

        // Aggregate planned amounts by cost_element_id
        $planned = [];

        foreach ($budgets as $budget) {
            foreach ($budget->lines as $line) {
                // Filter by period if provided; null period on a line means full-year
                if ($period !== null && $line->period !== null && $line->period !== $period) {
                    continue;
                }

                $elementId   = $line->cost_element_id;
                $elementName = $line->costElement?->name ?? 'Unknown';
                $key         = $elementId ?? 0;

                if (!isset($planned[$key])) {
                    $planned[$key] = [
                        'cost_element_id' => $elementId,
                        'name'            => $elementName,
                        'planned'         => 0.0,
                    ];
                }

                $planned[$key]['planned'] += (float) ($line->budgeted_amount ?? 0);
            }
        }

        // ------------------------------------------------------------------
        // 2. Load actual postings from journal_entry_lines
        // ------------------------------------------------------------------
        $actualQuery = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.organization_id', $costCenter->organization_id)
            ->where('je.status', JournalEntry::STATUS_POSTED)
            ->where('jel.cost_center_id', $costCenter->id)
            ->select(
                'jel.cost_element_id',
                DB::raw('SUM(jel.debit) - SUM(jel.credit) AS net_actual')
            )
            ->groupBy('jel.cost_element_id');

        // Apply date range based on fiscal year / period
        if ($period !== null) {
            // Month-based: YYYY-{period}-01 to end of month
            $dateFrom = sprintf('%04d-%02d-01', $fiscalYear, $period);
            $dateTo   = date('Y-m-t', strtotime($dateFrom));
            $actualQuery->whereDate('je.entry_date', '>=', $dateFrom)
                        ->whereDate('je.entry_date', '<=', $dateTo);
        } else {
            $actualQuery->whereYear('je.entry_date', $fiscalYear);
        }

        $actuals = $actualQuery->get()->keyBy('cost_element_id');

        // ------------------------------------------------------------------
        // 3. Resolve cost element names for actuals that have no plan line
        // ------------------------------------------------------------------
        $elementIds = $actuals->keys()->filter(fn ($id) => $id !== null)->all();
        $elementNames = [];

        if (!empty($elementIds)) {
            $elementNames = CostElement::withoutGlobalScopes()
                ->whereIn('id', $elementIds)
                ->pluck('name', 'id')
                ->all();
        }

        // ------------------------------------------------------------------
        // 4. Merge plan and actual
        // ------------------------------------------------------------------
        // Seed with all planned entries
        $merged = $planned;

        // Add/merge actual entries
        foreach ($actuals as $elementId => $row) {
            $key    = $elementId ?? 0;
            $actual = (float) $row->net_actual;

            if (isset($merged[$key])) {
                $merged[$key]['actual'] = $actual;
            } else {
                $merged[$key] = [
                    'cost_element_id' => $elementId !== null ? (int) $elementId : null,
                    'name'            => $elementNames[$elementId] ?? 'Unknown',
                    'planned'         => 0.0,
                    'actual'          => $actual,
                ];
            }
        }

        // Ensure 'actual' key is present on plan-only rows
        foreach ($merged as &$row) {
            $row['actual'] = $row['actual'] ?? 0.0;
        }
        unset($row);

        // ------------------------------------------------------------------
        // 5. Calculate variances and totals
        // ------------------------------------------------------------------
        $byElement = [];

        foreach ($merged as $row) {
            $planned   = (float) ($row['planned'] ?? 0);
            $actual    = (float) ($row['actual']  ?? 0);
            $variance  = $planned - $actual;

            $byElement[] = [
                'cost_element_id' => $row['cost_element_id'],
                'name'            => $row['name'],
                'planned'         => $planned,
                'actual'          => $actual,
                'variance'        => $variance,
                'variance_pct'    => $planned != 0 ? round(($variance / $planned) * 100, 2) : null,
            ];
        }

        $totalPlanned  = array_sum(array_column($byElement, 'planned'));
        $totalActual   = array_sum(array_column($byElement, 'actual'));
        $totalVariance = $totalPlanned - $totalActual;

        return [
            'cost_center_id'  => $costCenter->id,
            'fiscal_year'     => $fiscalYear,
            'period'          => $period,
            'total_planned'   => $totalPlanned,
            'total_actual'    => $totalActual,
            'total_variance'  => $totalVariance,
            'utilization_pct' => $totalPlanned > 0
                ? round(($totalActual / $totalPlanned) * 100, 2)
                : null,
            'by_cost_element' => $byElement,
        ];
    }

    // ----------------------------------------------------------------
    // Period Planning
    // ----------------------------------------------------------------

    /**
     * Upsert a period-level plan entry for a cost center.
     *
     * Creates or updates a CostCenterBudgetLine row for the given
     * cost center / fiscal year / period / cost element combination.
     */
    public function setPeriodPlan(
        CostCenter $costCenter,
        int $fiscalYear,
        int $period,
        int $costElementId,
        float $amount
    ): CostCenterBudgetLine {
        return DB::transaction(function () use ($costCenter, $fiscalYear, $period, $costElementId, $amount): CostCenterBudgetLine {
            if ($period < 1 || $period > 12) {
                throw new InvalidArgumentException('Period must be between 1 and 12.');
            }

            if ($amount < 0) {
                throw new InvalidArgumentException('Plan amount cannot be negative.');
            }

            // Find or create the parent budget record for this year
            $budget = CostCenterBudget::firstOrCreate(
                [
                    'organization_id' => $costCenter->organization_id,
                    'cost_center_id'  => $costCenter->id,
                    'fiscal_year'     => $fiscalYear,
                    'budget_version'  => 'PLAN',
                ],
                [
                    'total_budget' => 0,
                    'currency'     => 'SAR',
                    'status'       => CostCenterBudget::STATUS_DRAFT,
                ]
            );

            /** @var CostCenterBudgetLine $line */
            $line = CostCenterBudgetLine::updateOrCreate(
                [
                    'cost_center_budget_id' => $budget->id,
                    'period'                => $period,
                    'cost_element_id'       => $costElementId,
                ],
                [
                    'budgeted_amount'  => $amount,
                    'committed_amount' => 0,
                    'actual_amount'    => 0,
                ]
            );

            // Recalculate total_budget on the parent
            $total = CostCenterBudgetLine::where('cost_center_budget_id', $budget->id)->sum('budgeted_amount');
            $budget->update(['total_budget' => $total]);

            return $line->fresh(['costElement:id,code,name']);
        });
    }

    /**
     * Return all 12 periods' planned amounts by cost element for a cost center / fiscal year.
     *
     * @return array{fiscal_year: int, periods: array<int, array<int, array{cost_element_id: int, name: string, period: int, planned: float}>>}
     */
    public function getPeriodPlan(CostCenter $costCenter, int $fiscalYear): array
    {
        $budgets = CostCenterBudget::where('cost_center_id', $costCenter->id)
            ->where('fiscal_year', $fiscalYear)
            ->with('lines.costElement')
            ->get();

        // Build a matrix: period => cost_element_id => row
        $matrix = [];
        for ($p = 1; $p <= 12; $p++) {
            $matrix[$p] = [];
        }

        foreach ($budgets as $budget) {
            foreach ($budget->lines as $line) {
                $p = (int) ($line->period ?? 0);

                if ($p < 1 || $p > 12) {
                    continue;
                }

                $elementId = (int) $line->cost_element_id;

                if (!isset($matrix[$p][$elementId])) {
                    $matrix[$p][$elementId] = [
                        'cost_element_id' => $elementId,
                        'name'            => $line->costElement?->name ?? 'Unknown',
                        'period'          => $p,
                        'planned'         => 0.0,
                    ];
                }

                $matrix[$p][$elementId]['planned'] += (float) ($line->budgeted_amount ?? 0);
            }
        }

        return [
            'fiscal_year' => $fiscalYear,
            'periods'     => $matrix,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function validateUniqueCode(int $orgId, string $code, ?int $excludeId = null): void
    {
        $query = CostCenter::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('code', $code)
            ->whereNull('deleted_at');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "A cost center with code [{$code}] already exists in this organization."
            );
        }
    }

    private function validateAllocationAmounts(array $data): void
    {
        $method = $data['allocation_method'] ?? CostAllocation::METHOD_PERCENTAGE;

        if ($method === CostAllocation::METHOD_PERCENTAGE) {
            if (empty($data['allocation_percent'])) {
                throw new InvalidArgumentException(
                    'allocation_percent is required for percentage method.'
                );
            }

            $pct = (float) $data['allocation_percent'];

            if ($pct <= 0 || $pct > 100) {
                throw new InvalidArgumentException(
                    'allocation_percent must be between 0.01 and 100.'
                );
            }
        }

        if ($method === CostAllocation::METHOD_FIXED) {
            if (empty($data['allocation_amount']) || (float) $data['allocation_amount'] <= 0) {
                throw new InvalidArgumentException(
                    'allocation_amount must be positive for fixed method.'
                );
            }
        }
    }

    /**
     * Resolve the monetary amount to allocate.
     * For percentage allocations we look up the actual spend on the source cost center
     * in the allocation period and apply the percentage.
     */
    private function resolveAllocationAmount(CostAllocation $allocation): float
    {
        if ($allocation->allocation_method === CostAllocation::METHOD_FIXED) {
            return (float) $allocation->allocation_amount;
        }

        if ($allocation->allocation_method === CostAllocation::METHOD_PERCENTAGE) {
            // Sum debits on journal lines tagged to the from_cost_center in the period
            $totalSpend = (float) DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('je.organization_id', $allocation->organization_id)
                ->where('je.status', JournalEntry::STATUS_POSTED)
                ->whereDate('je.entry_date', '>=', $allocation->period_start->toDateString())
                ->whereDate('je.entry_date', '<=', $allocation->period_end->toDateString())
                ->where('jel.cost_center_id', $allocation->from_cost_center_id)
                ->sum('jel.debit');

            return (float) bcdiv(bcmul((string) $totalSpend, (string) $allocation->allocation_percent, 8), '100', 4);
        }

        // activity method: use the stored allocation_amount directly
        return (float) ($allocation->allocation_amount ?? 0);
    }
}
