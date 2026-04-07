<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Projects\ProjectBillingMilestone;
use App\Models\Projects\ProjectBillingRule;
use App\Models\Projects\ProjectRevenueRecognition;
use Illuminate\Support\Str;

class ProjectInvoicingService
{
    public function createBillingRule(array $data): ProjectBillingRule
    {
        return ProjectBillingRule::create([
            'uuid'                 => Str::uuid(),
            'organization_id'      => $data['organization_id'],
            'project_id'           => $data['project_id'],
            'billing_type'         => $data['billing_type'],
            'currency'             => $data['currency'],
            'customer_id'          => $data['customer_id'] ?? null,
            'total_contract_value' => $data['total_contract_value'] ?? null,
            'retention_percentage' => $data['retention_percentage'] ?? 0,
        ]);
    }

    public function addMilestone(ProjectBillingRule $rule, array $data): ProjectBillingMilestone
    {
        return ProjectBillingMilestone::create([
            'uuid'                    => Str::uuid(),
            'project_billing_rule_id' => $rule->id,
            'milestone_name'          => $data['milestone_name'],
            'billing_amount'          => $data['billing_amount'],
            'billing_percentage'      => $data['billing_percentage'] ?? null,
            'due_date'                => $data['due_date'] ?? null,
            'status'                  => 'pending',
        ]);
    }

    public function recognizeRevenue(int $projectId, int $orgId, int $year, int $month, array $data): ProjectRevenueRecognition
    {
        return ProjectRevenueRecognition::create([
            'uuid'                   => Str::uuid(),
            'organization_id'        => $orgId,
            'project_id'             => $projectId,
            'period_year'            => $year,
            'period_month'           => $month,
            'recognized_revenue'     => $data['recognized_revenue'],
            'recognized_cost'        => $data['recognized_cost'],
            'completion_percentage'  => $data['completion_percentage'],
            'gl_account_id'          => $data['gl_account_id'] ?? null,
            'posted_at'              => $data['post'] ?? false ? now() : null,
        ]);
    }
}
