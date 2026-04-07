<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectCostEntry;
use App\Models\Projects\WbsElement;

class ProjectCostReportingService
{
    /**
     * Variance report: planned vs actual by WBS element and cost type.
     *
     * @return array{
     *   project_id: int,
     *   total_planned: float|string,
     *   total_actual: float,
     *   total_variance: string,
     *   wbs_breakdown: list<array<string, mixed>>
     * }
     */
    public function getVarianceReport(int $projectId): array
    {
        $wbsElements = WbsElement::query()
            ->where('project_id', $projectId)
            ->orderBy('wbs_code')
            ->get();

        $actualsByWbs = ProjectCostEntry::query()
            ->selectRaw('wbs_element_id, cost_type, SUM(amount) as actual_total')
            ->where('project_id', $projectId)
            ->groupBy('wbs_element_id', 'cost_type')
            ->get()
            ->groupBy('wbs_element_id');

        $rows = [];
        foreach ($wbsElements as $wbs) {
            $actuals = $actualsByWbs->get($wbs->id, collect());

            /** @var array<string, float> $actualByType */
            $actualByType = $actuals->pluck('actual_total', 'cost_type')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            $totalActual = array_sum($actualByType);
            $planned     = (float) $wbs->planned_cost;
            $variance    = bcsub((string) $planned, (string) $totalActual, 4);

            $rows[] = [
                'wbs_id'        => $wbs->id,
                'wbs_code'      => $wbs->wbs_code,
                'wbs_name'      => $wbs->name,
                'planned_cost'  => $planned,
                'actual_cost'   => $totalActual,
                'variance'      => $variance,
                'variance_pct'  => $planned > 0.0
                    ? round((float) $variance / $planned * 100, 2)
                    : 0.0,
                'by_cost_type'  => $actualByType,
                'progress_pct'  => $wbs->progress_percent,
            ];
        }

        $totalPlanned = (float) $wbsElements->sum('planned_cost');
        $totalActual  = array_sum(array_column($rows, 'actual_cost'));

        return [
            'project_id'     => $projectId,
            'total_planned'  => $totalPlanned,
            'total_actual'   => $totalActual,
            'total_variance' => bcsub((string) $totalPlanned, (string) $totalActual, 4),
            'wbs_breakdown'  => $rows,
        ];
    }

    /**
     * Cost trend: monthly actual spend with cumulative total.
     *
     * @return array{
     *   project_id: int,
     *   periods_shown: int,
     *   total_spent_to_date: string,
     *   monthly_trend: list<array{month: string, monthly: float, cumulative: string}>
     * }
     */
    public function getCostTrend(int $projectId, int $months = 12): array
    {
        $monthly = ProjectCostEntry::query()
            ->selectRaw("DATE_FORMAT(cost_date, '%Y-%m') as month, SUM(amount) as total")
            ->where('project_id', $projectId)
            ->groupBy('month')
            ->orderBy('month')
            ->limit($months)
            ->get();

        $cumulativeTotal = '0';

        $trend = $monthly->map(function (object $row) use (&$cumulativeTotal): array {
            $cumulativeTotal = bcadd($cumulativeTotal, (string) $row->total, 4);

            return [
                'month'      => $row->month,
                'monthly'    => (float) $row->total,
                'cumulative' => $cumulativeTotal,
            ];
        })->values()->all();

        return [
            'project_id'          => $projectId,
            'periods_shown'       => count($trend),
            'total_spent_to_date' => $cumulativeTotal,
            'monthly_trend'       => $trend,
        ];
    }

    /**
     * Cost by type: breakdown of spend by labor, material, equipment, etc.
     *
     * @return array{
     *   project_id: int,
     *   by_type: \Illuminate\Support\Collection,
     *   total: float
     * }
     */
    public function getCostByType(int $projectId): array
    {
        $byType = ProjectCostEntry::query()
            ->selectRaw('cost_type, SUM(amount) as total, COUNT(*) as entry_count')
            ->where('project_id', $projectId)
            ->groupBy('cost_type')
            ->orderByDesc('total')
            ->get();

        return [
            'project_id' => $projectId,
            'by_type'    => $byType,
            'total'      => (float) $byType->sum('total'),
        ];
    }
}
