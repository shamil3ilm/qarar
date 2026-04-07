<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectResourcePlan;
use App\Models\Projects\ProjectTimeSheet;
use Illuminate\Support\Str;

class ProjectResourceService
{
    public function planResource(array $data): ProjectResourcePlan
    {
        return ProjectResourcePlan::create([
            'uuid'                 => Str::uuid(),
            'organization_id'      => $data['organization_id'],
            'project_id'           => $data['project_id'],
            'wbs_element'          => $data['wbs_element'] ?? null,
            'resource_type'        => $data['resource_type'],
            'resource_id'          => $data['resource_id'] ?? null,
            'resource_description' => $data['resource_description'],
            'planned_quantity'     => $data['planned_quantity'],
            'uom'                  => $data['uom'],
            'planned_start'        => $data['planned_start'],
            'planned_end'          => $data['planned_end'],
            'cost_rate'            => $data['cost_rate'] ?? null,
            'planned_cost'         => $data['planned_cost'] ?? null,
        ]);
    }

    public function submitTimesheet(array $data): ProjectTimeSheet
    {
        return ProjectTimeSheet::create([
            'uuid'                 => Str::uuid(),
            'organization_id'      => $data['organization_id'],
            'employee_id'          => $data['employee_id'],
            'project_id'           => $data['project_id'],
            'wbs_element'          => $data['wbs_element'] ?? null,
            'work_date'            => $data['work_date'],
            'hours_worked'         => $data['hours_worked'],
            'activity_description' => $data['activity_description'] ?? null,
            'status'               => 'submitted',
        ]);
    }

    public function approveTimesheet(ProjectTimeSheet $sheet, int $approverId): void
    {
        $sheet->update([
            'status'      => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    public function getResourceUtilization(int $projectId): array
    {
        $plans = ProjectResourcePlan::where('project_id', $projectId)->get();

        return $plans->map(fn ($plan) => [
            'resource_type'        => $plan->resource_type,
            'resource_description' => $plan->resource_description,
            'planned_quantity'     => $plan->planned_quantity,
            'actual_quantity'      => $plan->actual_quantity,
            'utilization_pct'      => $plan->planned_quantity > 0
                ? round(($plan->actual_quantity / $plan->planned_quantity) * 100, 2)
                : 0,
            'planned_cost'  => $plan->planned_cost,
            'actual_cost'   => $plan->actual_cost,
        ])->toArray();
    }
}
