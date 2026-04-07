<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\AuditChecklist;
use App\Models\Manufacturing\AuditFinding;
use App\Models\Manufacturing\AuditPlan;
use App\Models\Manufacturing\AuditReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = AuditPlan::where('organization_id', $request->user()->organization_id)
            ->with('leadAuditor')
            ->paginate(20);

        return $this->paginated($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_number'      => 'required|string|max:50|unique:audit_plans',
            'title'            => 'required|string|max:255',
            'audit_type'       => 'required|in:internal,supplier,customer,regulatory,certification',
            'planned_start'    => 'required|date',
            'planned_end'      => 'required|date|after_or_equal:planned_start',
            'lead_auditor_id'  => 'nullable|integer|exists:users,id',
            'scope'            => 'nullable|string',
            'objectives'       => 'nullable|string',
        ]);

        $data['uuid']            = (string) Str::uuid();
        $data['organization_id'] = $request->user()->organization_id;

        $plan = AuditPlan::create($data);

        return $this->created($plan);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = AuditPlan::where('organization_id', $request->user()->organization_id)
            ->with(['leadAuditor', 'checklists', 'findings'])
            ->findOrFail($id);

        return $this->success($plan);
    }

    public function addChecklist(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'item_number' => 'required|string|max:20',
            'question'    => 'required|string',
        ]);

        $plan     = AuditPlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $checklist = $plan->checklists()->create(array_merge($data, ['uuid' => (string) Str::uuid()]));

        return $this->created($checklist);
    }

    public function updateChecklist(Request $request, int $planId, int $checklistId): JsonResponse
    {
        $plan      = AuditPlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $checklist = AuditChecklist::where('audit_plan_id', $plan->id)->findOrFail($checklistId);

        $data = $request->validate([
            'response' => 'required|in:yes,no,partial,na',
            'remarks'  => 'nullable|string',
        ]);

        $checklist->update($data);

        return $this->success($checklist, 'Checklist item updated');
    }

    public function addFinding(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'finding_number'          => 'required|string|max:30',
            'finding_type'            => 'required|in:major_nc,minor_nc,observation,positive',
            'description'             => 'required|string',
            'requirement_reference'   => 'nullable|string',
            'evidence'                => 'nullable|string',
            'due_date'                => 'nullable|date',
        ]);

        $plan    = AuditPlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $finding = $plan->findings()->create(array_merge($data, ['uuid' => (string) Str::uuid()]));

        return $this->created($finding);
    }

    public function closeFinding(Request $request, int $planId, int $findingId): JsonResponse
    {
        $plan    = AuditPlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $finding = AuditFinding::where('audit_plan_id', $plan->id)->findOrFail($findingId);

        $finding->update(['status' => 'closed']);

        return $this->success($finding, 'Finding closed');
    }

    public function createReport(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'report_date'       => 'required|date',
            'executive_summary' => 'nullable|string',
            'conclusions'       => 'nullable|string',
            'overall_rating'    => 'nullable|in:satisfactory,needs_improvement,unsatisfactory',
        ]);

        $plan   = AuditPlan::where('organization_id', $request->user()->organization_id)->findOrFail($planId);
        $report = AuditReport::create(array_merge($data, [
            'uuid'          => (string) Str::uuid(),
            'audit_plan_id' => $plan->id,
        ]));

        $plan->update(['status' => 'completed']);

        return $this->created($report);
    }
}
