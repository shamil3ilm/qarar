<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\EarnedValueSnapshot;
use App\Models\Projects\Project;
use App\Models\Projects\WbsElement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EarnedValueService
{
    /**
     * Compute all EVM metrics for a project at a given date and persist a snapshot.
     *
     * Metrics derived:
     *   PV  = sum of planned_cost of WBS elements whose planned_end_date <= snapshot_date
     *   EV  = sum of (planned_cost × progress_percent / 100) across all WBS elements
     *   AC  = sum of actual_cost across all WBS elements
     *   BAC = sum of planned_cost across all WBS elements
     *   SV  = EV - PV
     *   CV  = EV - AC
     *   SPI = EV / PV  (1.0 when PV = 0)
     *   CPI = EV / AC  (1.0 when AC = 0)
     *   EAC = BAC / CPI
     *   ETC = EAC - AC
     *   VAC = BAC - EAC
     */
    public function calculateSnapshot(int $projectId, string $date): EarnedValueSnapshot
    {
        return DB::transaction(function () use ($projectId, $date): EarnedValueSnapshot {
            $elements = WbsElement::where('project_id', $projectId)->get();

            $bac = (float) $elements->sum('planned_cost');

            // PV: planned value — work scheduled to be done by $date
            $pv = (float) $elements
                ->filter(fn (WbsElement $el) => $el->planned_end_date !== null && $el->planned_end_date->lte($date))
                ->sum('planned_cost');

            // EV: earned value — budgeted cost of work actually performed
            $ev = (float) $elements->sum(
                fn (WbsElement $el) => (float) $el->planned_cost * ((float) $el->progress_percent / 100)
            );

            // AC: actual cost of work performed
            $ac = (float) $elements->sum('actual_cost');

            $sv  = $ev - $pv;
            $cv  = $ev - $ac;
            $spi = $pv > 0.0 ? round($ev / $pv, 4) : 1.0;
            $cpi = $ac > 0.0 ? round($ev / $ac, 4) : 1.0;
            $eac = $cpi > 0.0 ? round($bac / $cpi, 4) : $bac;
            $etc = $eac - $ac;
            $vac = $bac - $eac;

            return EarnedValueSnapshot::updateOrCreate(
                ['project_id' => $projectId, 'snapshot_date' => $date],
                [
                    'budget_at_completion'       => $bac,
                    'planned_value'              => $pv,
                    'earned_value'               => $ev,
                    'actual_cost'                => $ac,
                    'schedule_variance'          => $sv,
                    'cost_variance'              => $cv,
                    'schedule_performance_index' => $spi,
                    'cost_performance_index'     => $cpi,
                    'estimate_at_completion'     => $eac,
                    'estimate_to_complete'       => $etc,
                    'variance_at_completion'     => $vac,
                ]
            );
        });
    }

    /**
     * Return the most recent EVM snapshot for a project.
     */
    public function getLatestSnapshot(int $projectId): ?EarnedValueSnapshot
    {
        return EarnedValueSnapshot::where('project_id', $projectId)
            ->orderByDesc('snapshot_date')
            ->first();
    }

    /**
     * Return all EVM snapshots for a project ordered chronologically for trend analysis.
     *
     * @return Collection<int, EarnedValueSnapshot>
     */
    public function getHistory(int $projectId): Collection
    {
        return EarnedValueSnapshot::where('project_id', $projectId)
            ->orderBy('snapshot_date')
            ->get();
    }

    // ── EVM trending & forecasting (Gap 3) ────────────────────────────────────

    /**
     * Convenience wrapper: compute and persist a snapshot for today's date.
     */
    public function computeSnapshot(int $projectId): EarnedValueSnapshot
    {
        return $this->calculateSnapshot($projectId, now()->toDateString());
    }

    /**
     * Return EVM snapshots trend for the last N periods with health indicators
     * and an EAC / schedule risk forecast derived from the latest snapshot.
     *
     * @return array{
     *   project_id: int,
     *   latest_snapshot: EarnedValueSnapshot|null,
     *   health_status: string,
     *   is_behind_schedule: bool,
     *   is_over_budget: bool,
     *   trend: list<array<string, mixed>>,
     *   forecast: array<string, mixed>
     * }
     */
    public function getTrend(int $projectId, int $snapshots = 12): array
    {
        $trend = EarnedValueSnapshot::query()
            ->where('project_id', $projectId)
            ->orderByDesc('snapshot_date')
            ->limit($snapshots)
            ->get()
            ->sortBy('snapshot_date')
            ->values();

        /** @var EarnedValueSnapshot|null $latest */
        $latest = $trend->last();

        return [
            'project_id'         => $projectId,
            'latest_snapshot'    => $latest,
            'health_status'      => $latest?->getHealthStatus() ?? 'unknown',
            'is_behind_schedule' => $latest?->isBehindSchedule() ?? false,
            'is_over_budget'     => $latest?->isOverBudget() ?? false,
            'trend'              => $trend->map(fn (EarnedValueSnapshot $s): array => [
                'date'                   => $s->snapshot_date?->toDateString(),
                'planned_value'          => $s->planned_value,
                'earned_value'           => $s->earned_value,
                'actual_cost'            => $s->actual_cost,
                'spi'                    => $s->schedule_performance_index,
                'cpi'                    => $s->cost_performance_index,
                'estimate_at_completion' => $s->estimate_at_completion,
                'variance_at_completion' => $s->variance_at_completion,
            ])->values()->all(),
            'forecast' => $this->buildForecast($latest),
        ];
    }

    /**
     * Build a forward-looking forecast from the latest EVM snapshot.
     *
     * Returns EAC under three scenarios plus TCPI and risk ratings.
     *
     * @return array<string, mixed>
     */
    private function buildForecast(?EarnedValueSnapshot $latest): array
    {
        if ($latest === null) {
            return [];
        }

        $cpi = (float) $latest->cost_performance_index;
        $spi = (float) $latest->schedule_performance_index;
        $bac = (float) $latest->budget_at_completion;
        $ac  = (float) $latest->actual_cost;
        $ev  = (float) $latest->earned_value;

        // EAC driven by CPI alone
        $eacCpi = $cpi > 0.0 ? $bac / $cpi : $bac;

        // EAC composite (CPI × SPI) — pessimistic when both indices < 1
        $eacSpiCpi = ($cpi * $spi) > 0.0 ? $bac / ($cpi * $spi) : $bac;

        // To-Complete Performance Index (TCPI): efficiency needed for remaining budget
        $remainingBudget = $bac - $ac;
        $remainingWork   = $bac - $ev;
        $tcpi = $remainingBudget > 0.0
            ? $remainingWork / $remainingBudget
            : 1.0;

        return [
            'eac_cpi'           => round($eacCpi, 4),
            'eac_spi_cpi'       => round($eacSpiCpi, 4),
            'to_complete_cpi'   => round($tcpi, 4),
            'cost_overrun_risk' => $cpi < 0.9 ? 'high' : ($cpi < 1.0 ? 'medium' : 'low'),
            'schedule_risk'     => $spi < 0.9 ? 'high' : ($spi < 1.0 ? 'medium' : 'low'),
        ];
    }
}
