<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\EvmBaseline;
use App\Models\Projects\EvmBaselineLine;
use App\Models\Projects\EarnedValueSnapshot;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * EVM Baseline Service — SAP PS project baseline management.
 *
 * A baseline freezes planned cost + schedule at a point in time.
 * EVM metrics (SV, CV, SPI, CPI) are then measured against the
 * active baseline, not the current plan.
 *
 * Supports SAP PS concepts:
 *  - Original baseline (set at project approval)
 *  - Re-baseline (approved revision after scope change)
 *  - Baseline comparison report (planned vs actual vs baseline)
 */
class EvmBaselineService
{
    /**
     * Capture the current project plan as a new baseline.
     * If $setAsActive=true, deactivates all other baselines for this project.
     */
    public function capture(
        Project $project,
        string $name,
        string $baselineType,
        bool $setAsActive,
        User $createdBy,
        ?string $notes = null,
    ): EvmBaseline {
        return DB::transaction(function () use ($project, $name, $baselineType, $setAsActive, $createdBy, $notes): EvmBaseline {
            if ($setAsActive) {
                EvmBaseline::where('project_id', $project->id)->update(['is_active' => false]);
            }

            $baseline = EvmBaseline::create([
                'organization_id'       => $project->organization_id,
                'project_id'            => $project->id,
                'name'                  => $name,
                'baseline_type'         => $baselineType,
                'baseline_date'         => today()->toDateString(),
                'planned_cost'          => $project->budget ?? 0,
                'planned_duration_days' => $project->start_date && $project->end_date
                    ? $project->start_date->diffInDays($project->end_date)
                    : 0,
                'planned_start'         => $project->start_date ?? today(),
                'planned_finish'        => $project->end_date ?? today(),
                'is_active'             => $setAsActive,
                'notes'                 => $notes,
                'created_by'            => $createdBy->id,
            ]);

            // Capture WBS-level baseline lines
            $this->captureWbsLines($baseline, $project);

            return $baseline->load('lines');
        });
    }

    /**
     * Approve a baseline (manager sign-off before it becomes authoritative).
     */
    public function approve(EvmBaseline $baseline, User $approver): EvmBaseline
    {
        $baseline->update([
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $baseline->fresh();
    }

    /**
     * Activate a baseline — deactivates all others for the same project.
     */
    public function activate(EvmBaseline $baseline): EvmBaseline
    {
        DB::transaction(function () use ($baseline): void {
            EvmBaseline::where('project_id', $baseline->project_id)
                ->where('id', '!=', $baseline->id)
                ->update(['is_active' => false]);

            $baseline->update(['is_active' => true]);
        });

        return $baseline->fresh();
    }

    /**
     * Compare latest EVM snapshot against the active baseline.
     *
     * @return array{
     *     baseline: EvmBaseline,
     *     latest_snapshot: EarnedValueSnapshot|null,
     *     baseline_variance: array,
     * }
     */
    public function compareToBaseline(Project $project): array
    {
        $baseline = EvmBaseline::where('project_id', $project->id)
            ->where('is_active', true)
            ->first();

        $latestSnapshot = EarnedValueSnapshot::where('project_id', $project->id)
            ->latest('snapshot_date')
            ->first();

        if (! $baseline || ! $latestSnapshot) {
            return [
                'baseline'           => $baseline,
                'latest_snapshot'    => $latestSnapshot,
                'baseline_variance'  => [],
            ];
        }

        $bac = (float) $baseline->planned_cost;
        $ev  = (float) $latestSnapshot->earned_value;
        $ac  = (float) $latestSnapshot->actual_cost;
        $pv  = (float) $latestSnapshot->planned_value;

        return [
            'baseline'        => $baseline,
            'latest_snapshot' => $latestSnapshot,
            'baseline_variance' => [
                'cost_variance_from_baseline'     => round($bac - $ac, 2),
                'cost_variance_pct'               => $bac > 0 ? round(($bac - $ac) / $bac * 100, 2) : 0,
                'schedule_variance_from_baseline' => round($ev - $pv, 2),
                'planned_vs_actual_cost'          => round($pv - $ac, 2),
                'health_status'                   => $latestSnapshot->getHealthStatus(),
            ],
        ];
    }

    /**
     * List all baselines for a project.
     */
    public function getBaselines(int $projectId): \Illuminate\Database\Eloquent\Collection
    {
        return EvmBaseline::where('project_id', $projectId)
            ->with(['creator:id,name', 'approver:id,name'])
            ->orderByDesc('baseline_date')
            ->get();
    }

    // ----------------------------------------------------------------

    private function captureWbsLines(EvmBaseline $baseline, Project $project): void
    {
        $wbsElements = $project->wbsElements ?? collect();

        foreach ($wbsElements as $wbs) {
            EvmBaselineLine::create([
                'baseline_id'           => $baseline->id,
                'wbs_id'                => $wbs->id,
                'planned_cost'          => $wbs->budget ?? 0,
                'planned_start'         => $wbs->start_date ?? $baseline->planned_start,
                'planned_finish'        => $wbs->end_date ?? $baseline->planned_finish,
                'planned_duration_days' => ($wbs->start_date && $wbs->end_date)
                    ? $wbs->start_date->diffInDays($wbs->end_date)
                    : 0,
            ]);
        }
    }
}
