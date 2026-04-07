<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectRevenuePlan;
use App\Models\Projects\ProjectRevenuePlanLine;
use Illuminate\Support\Str;

class ProjectRevenuePlanService
{
    public function createPlan(array $data): ProjectRevenuePlan
    {
        $plan = ProjectRevenuePlan::create([
            'uuid'                  => Str::uuid(),
            'organization_id'       => $data['organization_id'],
            'project_id'            => $data['project_id'],
            'fiscal_year'           => $data['fiscal_year'],
            'version'               => $data['version'] ?? '0',
            'status'                => 'draft',
            'total_planned_revenue' => 0,
            'total_planned_cost'    => 0,
            'currency'              => $data['currency'],
        ]);

        if (! empty($data['lines'])) {
            foreach ($data['lines'] as $line) {
                ProjectRevenuePlanLine::create([
                    'project_revenue_plan_id' => $plan->id,
                    'period_month'            => $line['period_month'],
                    'planned_revenue'         => $line['planned_revenue'] ?? 0,
                    'planned_cost'            => $line['planned_cost'] ?? 0,
                ]);
            }

            $plan->update([
                'total_planned_revenue' => collect($data['lines'])->sum('planned_revenue'),
                'total_planned_cost'    => collect($data['lines'])->sum('planned_cost'),
            ]);
        }

        return $plan->load('lines');
    }

    public function approvePlan(ProjectRevenuePlan $plan, int $userId): void
    {
        $plan->update(['status' => 'approved', 'approved_by' => $userId]);
    }

    public function getVarianceReport(int $projectId, int $year): array
    {
        $plans = ProjectRevenuePlan::where('project_id', $projectId)
            ->where('fiscal_year', $year)
            ->where('status', 'approved')
            ->with('lines')
            ->get();

        if ($plans->isEmpty()) {
            return ['message' => 'No approved plan found'];
        }

        $plan  = $plans->first();
        $lines = $plan->lines;

        return $lines->map(fn ($line) => [
            'period_month'       => $line->period_month,
            'planned_revenue'    => $line->planned_revenue,
            'actual_revenue'     => $line->actual_revenue,
            'revenue_variance'   => (float) $line->actual_revenue - (float) $line->planned_revenue,
            'planned_cost'       => $line->planned_cost,
            'actual_cost'        => $line->actual_cost,
            'cost_variance'      => (float) $line->actual_cost - (float) $line->planned_cost,
        ])->toArray();
    }
}
