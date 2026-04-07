<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Models\Projects\ProjectBillingMilestone;
use App\Models\Projects\ProjectBillingRule;
use App\Services\Projects\ProjectInvoicingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectInvoicingController extends Controller
{
    public function __construct(private readonly ProjectInvoicingService $service) {}

    public function billingRules(Request $request): JsonResponse
    {
        $rules = ProjectBillingRule::where('organization_id', $request->user()->organization_id)
            ->with(['customer', 'milestones'])
            ->paginate(20);

        return $this->paginated($rules);
    }

    public function storeBillingRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'           => 'required|integer',
            'billing_type'         => 'required|in:milestone,time_material,fixed_price,percentage_completion',
            'currency'             => 'required|string|size:3',
            'customer_id'          => 'nullable|integer',
            'total_contract_value' => 'nullable|numeric|min:0',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $rule = $this->service->createBillingRule($data);

        return $this->created($rule);
    }

    public function addMilestone(Request $request, int $ruleId): JsonResponse
    {
        $rule = ProjectBillingRule::where('organization_id', $request->user()->organization_id)->findOrFail($ruleId);
        $data = $request->validate([
            'milestone_name'     => 'required|string|max:255',
            'billing_amount'     => 'required|numeric|min:0',
            'billing_percentage' => 'nullable|numeric|min:0|max:100',
            'due_date'           => 'nullable|date',
        ]);

        $milestone = $this->service->addMilestone($rule, $data);

        return $this->created($milestone);
    }

    public function recognizeRevenue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'            => 'required|integer',
            'period_year'           => 'required|integer|min:2000|max:2100',
            'period_month'          => 'required|integer|min:1|max:12',
            'recognized_revenue'    => 'required|numeric|min:0',
            'recognized_cost'       => 'required|numeric|min:0',
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'gl_account_id'         => 'nullable|integer',
            'post'                  => 'boolean',
        ]);

        $recognition = $this->service->recognizeRevenue(
            $data['project_id'],
            $request->user()->organization_id,
            $data['period_year'],
            $data['period_month'],
            $data
        );

        return $this->created($recognition);
    }
}
