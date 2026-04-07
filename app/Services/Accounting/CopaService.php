<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\CopaLineItem;
use App\Models\Accounting\CopaPlannedLineItem;
use App\Models\Accounting\CopaPlanVersion;
use App\Models\Accounting\FiscalYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CopaService
{
    // ----------------------------------------------------------------
    // Posting
    // ----------------------------------------------------------------

    /**
     * Persist a CO-PA line item posting.
     * Called from InvoiceService (or any document service) when a document is confirmed.
     */
    public function recordLineItem(array $data): CopaLineItem
    {
        $this->validateLineItemData($data);

        return CopaLineItem::create($data);
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Aggregate profitability report grouped by product/customer/profit_center.
     *
     * Supported filter keys:
     *   organization_id (required), fiscal_year_id, period, from_date, to_date,
     *   profit_center_id, cost_center_id, product_id, contact_id
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function getProfitabilityReport(array $filters): array
    {
        $orgId = (int) ($filters['organization_id'] ?? 0);

        $query = DB::table('copa_line_items')
            ->where('organization_id', $orgId);

        $this->applyCommonFilters($query, $filters);

        $rows = $query
            ->select(
                'profit_center_id',
                'cost_center_id',
                'product_id',
                'contact_id',
                DB::raw('SUM(revenue)           AS total_revenue'),
                DB::raw('SUM(cogs)              AS total_cogs'),
                DB::raw('SUM(gross_profit)      AS total_gross_profit'),
                DB::raw('SUM(overhead_allocated) AS total_overhead'),
                DB::raw('SUM(net_profit)        AS total_net_profit'),
                DB::raw('COUNT(*)               AS line_count')
            )
            ->groupBy('profit_center_id', 'cost_center_id', 'product_id', 'contact_id')
            ->orderByDesc('total_revenue')
            ->get();

        $totals = [
            'revenue'      => (float) $rows->sum('total_revenue'),
            'cogs'         => (float) $rows->sum('total_cogs'),
            'gross_profit' => (float) $rows->sum('total_gross_profit'),
            'overhead'     => (float) $rows->sum('total_overhead'),
            'net_profit'   => (float) $rows->sum('total_net_profit'),
        ];

        return [
            'rows'   => $rows->map(fn (object $r): array => $this->mapRow($r))->toArray(),
            'totals' => $totals,
        ];
    }

    /**
     * CO-PA analysis grouped by a single dimension type.
     *
     * @param  string  $dimensionType  product_id | contact_id | profit_center_id | cost_center_id
     * @return array{dimension_type: string, rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function getDimensionBreakdown(string $dimensionType, array $filters): array
    {
        $allowedDimensions = ['product_id', 'contact_id', 'profit_center_id', 'cost_center_id'];

        if (!in_array($dimensionType, $allowedDimensions, true)) {
            throw new InvalidArgumentException(
                "Invalid dimension type [{$dimensionType}]. Allowed: " . implode(', ', $allowedDimensions)
            );
        }

        $orgId = (int) ($filters['organization_id'] ?? 0);

        $query = DB::table('copa_line_items')
            ->where('organization_id', $orgId);

        $this->applyCommonFilters($query, $filters);

        $rows = $query
            ->select(
                $dimensionType,
                DB::raw('SUM(revenue)           AS total_revenue'),
                DB::raw('SUM(cogs)              AS total_cogs'),
                DB::raw('SUM(gross_profit)      AS total_gross_profit'),
                DB::raw('SUM(overhead_allocated) AS total_overhead'),
                DB::raw('SUM(net_profit)        AS total_net_profit'),
                DB::raw('COUNT(*)               AS line_count')
            )
            ->groupBy($dimensionType)
            ->orderByDesc('total_revenue')
            ->get();

        $totals = [
            'revenue'      => (float) $rows->sum('total_revenue'),
            'cogs'         => (float) $rows->sum('total_cogs'),
            'gross_profit' => (float) $rows->sum('total_gross_profit'),
            'overhead'     => (float) $rows->sum('total_overhead'),
            'net_profit'   => (float) $rows->sum('total_net_profit'),
        ];

        return [
            'dimension_type' => $dimensionType,
            'rows'           => $rows->map(fn (object $r): array => [
                'dimension_key'   => $r->{$dimensionType},
                'total_revenue'   => (float) $r->total_revenue,
                'total_cogs'      => (float) $r->total_cogs,
                'total_gross_profit' => (float) $r->total_gross_profit,
                'total_overhead'  => (float) $r->total_overhead,
                'total_net_profit' => (float) $r->total_net_profit,
                'line_count'      => (int) $r->line_count,
            ])->toArray(),
            'totals' => $totals,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function applyCommonFilters(\Illuminate\Database\Query\Builder $query, array $filters): void
    {
        if (!empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', (int) $filters['fiscal_year_id']);
        }

        if (!empty($filters['period'])) {
            $query->where('period', (int) $filters['period']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('posting_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('posting_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['profit_center_id'])) {
            $query->where('profit_center_id', (int) $filters['profit_center_id']);
        }

        if (!empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', (int) $filters['cost_center_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }
    }

    private function validateLineItemData(array $data): void
    {
        if (empty($data['organization_id'])) {
            throw new InvalidArgumentException('organization_id is required for CO-PA posting.');
        }

        if (empty($data['posting_date'])) {
            throw new InvalidArgumentException('posting_date is required for CO-PA posting.');
        }

        if (empty($data['source_document_type'])) {
            throw new InvalidArgumentException('source_document_type is required for CO-PA posting.');
        }
    }

    private function mapRow(object $r): array
    {
        return [
            'profit_center_id'    => $r->profit_center_id,
            'cost_center_id'      => $r->cost_center_id,
            'product_id'          => $r->product_id,
            'contact_id'          => $r->contact_id,
            'total_revenue'       => (float) $r->total_revenue,
            'total_cogs'          => (float) $r->total_cogs,
            'total_gross_profit'  => (float) $r->total_gross_profit,
            'total_overhead'      => (float) $r->total_overhead,
            'total_net_profit'    => (float) $r->total_net_profit,
            'line_count'          => (int) $r->line_count,
        ];
    }

    // ----------------------------------------------------------------
    // Plan Data — Gap 2
    // ----------------------------------------------------------------

    /**
     * Create a new CO-PA plan version for the given fiscal year.
     *
     * @param  array<string, mixed>  $data  Must include: organization_id, fiscal_year_id, version_name
     */
    public function createPlanVersion(array $data): CopaPlanVersion
    {
        if (empty($data['organization_id'])) {
            throw new InvalidArgumentException('organization_id is required for CO-PA plan version.');
        }

        if (empty($data['fiscal_year_id'])) {
            throw new InvalidArgumentException('fiscal_year_id is required for CO-PA plan version.');
        }

        if (empty($data['version_name'])) {
            throw new InvalidArgumentException('version_name is required for CO-PA plan version.');
        }

        return CopaPlanVersion::create($data);
    }

    /**
     * Store a single planned line item under the given plan version.
     *
     * @param  array<string, mixed>  $data
     */
    public function storePlanLineItem(CopaPlanVersion $version, array $data): CopaPlannedLineItem
    {
        $this->validatePlanLineItemData($data);

        return CopaPlannedLineItem::create(array_merge($data, [
            'plan_version_id' => $version->id,
            'organization_id' => $version->organization_id,
        ]));
    }

    /**
     * Upsert a batch of planned line items for a plan version.
     *
     * Each element in $lines must include: period.
     * Uniqueness key: plan_version_id + period + profit_center_id + product_id + contact_id.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function bulkStorePlan(CopaPlanVersion $version, array $lines): void
    {
        DB::transaction(function () use ($version, $lines): void {
            foreach ($lines as $line) {
                $this->validatePlanLineItemData($line);

                CopaPlannedLineItem::updateOrCreate(
                    [
                        'plan_version_id'  => $version->id,
                        'period'           => (int) $line['period'],
                        'profit_center_id' => $line['profit_center_id'] ?? null,
                        'product_id'       => $line['product_id'] ?? null,
                        'contact_id'       => $line['contact_id'] ?? null,
                    ],
                    array_merge($line, [
                        'plan_version_id' => $version->id,
                        'organization_id' => $version->organization_id,
                    ])
                );
            }
        });
    }

    /**
     * Actual-vs-plan variance report.
     *
     * Joins copa_line_items (actual) with copa_planned_line_items (plan) on
     * period / profit_center_id / product_id for the specified plan version.
     *
     * Optional $filters keys: period, profit_center_id, product_id, contact_id
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function getVarianceReport(int $orgId, int $fiscalYearId, int $planVersionId, array $filters = []): array
    {
        $query = DB::table('copa_planned_line_items as plan')
            ->leftJoin('copa_line_items as actual', function ($join) use ($orgId, $fiscalYearId): void {
                $join->on('actual.period', '=', 'plan.period')
                    ->on('actual.profit_center_id', '=', 'plan.profit_center_id')
                    ->on('actual.product_id', '=', 'plan.product_id')
                    ->where('actual.organization_id', $orgId)
                    ->where('actual.fiscal_year_id', $fiscalYearId);
            })
            ->where('plan.plan_version_id', $planVersionId)
            ->where('plan.organization_id', $orgId);

        if (!empty($filters['period'])) {
            $query->where('plan.period', (int) $filters['period']);
        }

        if (!empty($filters['profit_center_id'])) {
            $query->where('plan.profit_center_id', (int) $filters['profit_center_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('plan.product_id', (int) $filters['product_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('plan.contact_id', (int) $filters['contact_id']);
        }

        $rows = $query->select([
            'plan.period',
            'plan.profit_center_id',
            'plan.product_id',
            'plan.contact_id',
            'plan.planned_revenue',
            'plan.planned_cogs',
            'plan.planned_gross_profit',
            'plan.planned_overhead',
            'plan.planned_net_profit',
            DB::raw('COALESCE(SUM(actual.revenue), 0)           AS actual_revenue'),
            DB::raw('COALESCE(SUM(actual.cogs), 0)              AS actual_cogs'),
            DB::raw('COALESCE(SUM(actual.gross_profit), 0)      AS actual_gross_profit'),
            DB::raw('COALESCE(SUM(actual.overhead_allocated), 0) AS actual_overhead'),
            DB::raw('COALESCE(SUM(actual.net_profit), 0)        AS actual_net_profit'),
        ])
        ->groupBy(
            'plan.period',
            'plan.profit_center_id',
            'plan.product_id',
            'plan.contact_id',
            'plan.planned_revenue',
            'plan.planned_cogs',
            'plan.planned_gross_profit',
            'plan.planned_overhead',
            'plan.planned_net_profit'
        )
        ->orderBy('plan.period')
        ->get();

        $mappedRows = $rows->map(function (object $r): array {
            $plannedRevenue = (float) $r->planned_revenue;
            $actualRevenue  = (float) $r->actual_revenue;

            return [
                'period'             => (int) $r->period,
                'profit_center_id'   => $r->profit_center_id,
                'product_id'         => $r->product_id,
                'contact_id'         => $r->contact_id,
                'planned_revenue'    => $plannedRevenue,
                'planned_cogs'       => (float) $r->planned_cogs,
                'planned_gross_profit' => (float) $r->planned_gross_profit,
                'planned_overhead'   => (float) $r->planned_overhead,
                'planned_net_profit' => (float) $r->planned_net_profit,
                'actual_revenue'     => $actualRevenue,
                'actual_cogs'        => (float) $r->actual_cogs,
                'actual_gross_profit' => (float) $r->actual_gross_profit,
                'actual_overhead'    => (float) $r->actual_overhead,
                'actual_net_profit'  => (float) $r->actual_net_profit,
                'revenue_variance'   => $actualRevenue - $plannedRevenue,
                'cogs_variance'      => (float) $r->actual_cogs - (float) $r->planned_cogs,
                'profit_variance'    => (float) $r->actual_net_profit - (float) $r->planned_net_profit,
                'variance_pct'       => $plannedRevenue != 0
                    ? round((($actualRevenue - $plannedRevenue) / $plannedRevenue) * 100, 2)
                    : null,
            ];
        })->toArray();

        $totals = [
            'planned_revenue'      => (float) $rows->sum('planned_revenue'),
            'actual_revenue'       => (float) $rows->sum('actual_revenue'),
            'revenue_variance'     => (float) $rows->sum('actual_revenue') - (float) $rows->sum('planned_revenue'),
            'planned_net_profit'   => (float) $rows->sum('planned_net_profit'),
            'actual_net_profit'    => (float) $rows->sum('actual_net_profit'),
            'profit_variance'      => (float) $rows->sum('actual_net_profit') - (float) $rows->sum('planned_net_profit'),
        ];

        return [
            'rows'   => $mappedRows,
            'totals' => $totals,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers (plan)
    // ----------------------------------------------------------------

    private function validatePlanLineItemData(array $data): void
    {
        if (empty($data['period']) || (int) $data['period'] < 1 || (int) $data['period'] > 12) {
            throw new InvalidArgumentException('period must be an integer between 1 and 12.');
        }
    }
}
