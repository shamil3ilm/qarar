<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\AuditFindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditFindingController extends Controller
{
    public function __construct(
        private readonly AuditFindingService $service
    ) {}

    public function indexEngagements(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $engagements = \App\Models\Compliance\AuditEngagement::with('leadAuditor')
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($engagements);
    }

    public function storeEngagement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'              => ['required', 'string', 'max:200'],
            'audit_type'         => ['required', 'in:internal,external,regulatory,it,operational,financial,compliance'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date'   => ['required', 'date', 'after_or_equal:planned_start_date'],
            'scope'              => ['nullable', 'string'],
            'objectives'         => ['nullable', 'string'],
            'lead_auditor_id'    => ['required', 'integer', 'exists:users,id'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $engagement = $this->service->createEngagement($organizationId, $data, $userId);

        return $this->created($engagement, 'Audit engagement created');
    }

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $filters = $request->only(['status', 'severity', 'due_date_from', 'due_date_to', 'overdue', 'engagement_id', 'per_page']);

        $paginator = $this->service->listFindings($organizationId, $filters);

        return $this->paginated($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'engagement_id'    => ['nullable', 'integer', 'exists:grc_audit_engagements,id'],
            'title'            => ['required', 'string', 'max:200'],
            'description'      => ['required', 'string'],
            'criteria'         => ['nullable', 'string'],
            'condition'        => ['nullable', 'string'],
            'cause'            => ['nullable', 'string'],
            'effect'           => ['nullable', 'string'],
            'recommendation'   => ['nullable', 'string'],
            'severity'         => ['required', 'in:critical,high,medium,low,informational'],
            'finding_type'     => ['required', 'in:control_deficiency,process_gap,policy_violation,fraud_risk,it_risk,compliance_gap'],
            'module_reference' => ['nullable', 'string', 'max:50'],
            'due_date'         => ['nullable', 'date'],
            'repeat_finding'   => ['boolean'],
            'parent_finding_id' => ['nullable', 'integer', 'exists:grc_audit_findings,id'],
            'actions'          => ['nullable', 'array'],
            'actions.*.title'  => ['required_with:actions', 'string', 'max:200'],
            'actions.*.due_date' => ['required_with:actions', 'date'],
            'actions.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $finding = $this->service->createFinding($organizationId, $data, $userId);

        return $this->created($finding, 'Audit finding created');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $finding = \App\Models\Compliance\AuditFinding::with(['engagement', 'owner', 'verifier', 'actions.assignee'])
            ->where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->success($finding);
    }

    public function assign(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'owner_id'                => ['required', 'integer', 'exists:users,id'],
            'remediation_target_date' => ['nullable', 'date'],
            'remediation_plan'        => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);

        $finding = $this->service->assignFinding(
            $organizationId,
            $uuid,
            $data['owner_id'],
            $data
        );

        return $this->success($finding, 'Finding assigned');
    }

    public function submitRemediation(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'remediation_plan' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $finding = $this->service->submitRemediation($organizationId, $uuid, $data, $userId);

        return $this->success($finding, 'Remediation submitted');
    }

    public function verify(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'verification_notes' => ['nullable', 'string'],
        ]);

        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $finding = $this->service->verifyRemediation($organizationId, $uuid, $data, $userId);

        return $this->success($finding, 'Remediation verified');
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $this->organizationId($request);
        $userId         = auth()->id();

        $finding = $this->service->closeFinding($organizationId, $uuid, $userId);

        return $this->success($finding, 'Finding closed');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        return $this->success($this->service->getDashboard($organizationId));
    }
}
