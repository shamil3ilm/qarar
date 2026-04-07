<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\ProfitabilitySegment;
use App\Models\Accounting\ProfitabilitySegmentValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProfitabilitySegmentService
{
    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ProfitabilitySegment::with(['customerGroup', 'product:id,name,sku'])
            ->orderBy('segment_name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('segment_name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): ProfitabilitySegment
    {
        return DB::transaction(fn (): ProfitabilitySegment => ProfitabilitySegment::create($data));
    }

    public function update(ProfitabilitySegment $segment, array $data): ProfitabilitySegment
    {
        return DB::transaction(function () use ($segment, $data): ProfitabilitySegment {
            $segment->update($data);

            return $segment->fresh();
        });
    }

    // ----------------------------------------------------------------
    // Values
    // ----------------------------------------------------------------

    public function postValues(array $data): ProfitabilitySegmentValue
    {
        return DB::transaction(function () use ($data): ProfitabilitySegmentValue {
            // Upsert by segment+dimension+period+year
            $existing = ProfitabilitySegmentValue::withoutGlobalScope('organization')
                ->where('organization_id', $data['organization_id'])
                ->where('profitability_segment_id', $data['profitability_segment_id'])
                ->where('copa_dimension_id', $data['copa_dimension_id'] ?? null)
                ->where('period', $data['period'])
                ->where('fiscal_year', $data['fiscal_year'])
                ->first();

            if ($existing !== null) {
                $existing->update($data);

                return $existing->fresh();
            }

            return ProfitabilitySegmentValue::create($data);
        });
    }

    // ----------------------------------------------------------------
    // Reporting
    // ----------------------------------------------------------------

    /**
     * Drill-down report grouped by the requested dimension.
     *
     * @param  string[] $dimensions  e.g. ['region', 'sales_channel']
     * @return array<string, array{dimension_value: string, revenue: float, gross_margin: float, net_margin: float}>
     */
    public function getDrillDown(array $dimensions, int $period, int $year): array
    {
        $groupByColumns = [];
        $selectColumns  = [];

        $allowedSegmentColumns = ['region', 'sales_channel', 'segment_name'];

        foreach ($dimensions as $dim) {
            if (in_array($dim, $allowedSegmentColumns, true)) {
                $groupByColumns[] = "ps.{$dim}";
                $selectColumns[]  = "ps.{$dim} as {$dim}";
            }
        }

        if (empty($groupByColumns)) {
            $groupByColumns = ['ps.segment_name'];
            $selectColumns  = ['ps.segment_name'];
        }

        $rows = DB::table('profitability_segment_values as psv')
            ->join('profitability_segments as ps', 'ps.id', '=', 'psv.profitability_segment_id')
            ->where('psv.period', $period)
            ->where('psv.fiscal_year', $year)
            ->whereNull('ps.deleted_at')
            ->select(array_merge(
                $selectColumns,
                [
                    DB::raw('SUM(psv.revenue)       AS revenue'),
                    DB::raw('SUM(psv.cost_of_sales) AS cost_of_sales'),
                    DB::raw('SUM(psv.gross_margin)  AS gross_margin'),
                    DB::raw('SUM(psv.net_margin)    AS net_margin'),
                    DB::raw('SUM(psv.quantity_sold) AS quantity_sold'),
                ]
            ))
            ->groupBy(...$groupByColumns)
            ->get();

        return $rows->map(function (object $row) use ($dimensions): array {
            $result = [
                'revenue'       => (float) $row->revenue,
                'cost_of_sales' => (float) $row->cost_of_sales,
                'gross_margin'  => (float) $row->gross_margin,
                'net_margin'    => (float) $row->net_margin,
                'quantity_sold' => (float) $row->quantity_sold,
            ];

            foreach ($dimensions as $dim) {
                $result[$dim] = $row->{$dim} ?? null;
            }

            return $result;
        })->toArray();
    }

    /**
     * Full segment report for a period/year — all segments with their values.
     *
     * @return array<int, array{segment_id: int, segment_name: string, revenue: float, gross_margin: float, net_margin: float}>
     */
    public function getSegmentReport(int $period, int $year): array
    {
        $rows = DB::table('profitability_segment_values as psv')
            ->join('profitability_segments as ps', 'ps.id', '=', 'psv.profitability_segment_id')
            ->where('psv.period', $period)
            ->where('psv.fiscal_year', $year)
            ->whereNull('ps.deleted_at')
            ->select(
                'ps.id as segment_id',
                'ps.segment_name',
                'ps.region',
                'ps.sales_channel',
                DB::raw('SUM(psv.revenue)         AS revenue'),
                DB::raw('SUM(psv.cost_of_sales)   AS cost_of_sales'),
                DB::raw('SUM(psv.gross_margin)    AS gross_margin'),
                DB::raw('SUM(psv.overhead_costs)  AS overhead_costs'),
                DB::raw('SUM(psv.net_margin)      AS net_margin'),
                DB::raw('SUM(psv.quantity_sold)   AS quantity_sold')
            )
            ->groupBy('ps.id', 'ps.segment_name', 'ps.region', 'ps.sales_channel')
            ->orderBy('ps.segment_name')
            ->get();

        return $rows->map(fn (object $row): array => [
            'segment_id'     => $row->segment_id,
            'segment_name'   => $row->segment_name,
            'region'         => $row->region,
            'sales_channel'  => $row->sales_channel,
            'revenue'        => (float) $row->revenue,
            'cost_of_sales'  => (float) $row->cost_of_sales,
            'gross_margin'   => (float) $row->gross_margin,
            'overhead_costs' => (float) $row->overhead_costs,
            'net_margin'     => (float) $row->net_margin,
            'quantity_sold'  => (float) $row->quantity_sold,
        ])->toArray();
    }
}
