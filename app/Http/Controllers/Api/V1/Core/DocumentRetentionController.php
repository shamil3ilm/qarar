<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\DocumentLegalHold;
use App\Models\Core\RetentionPolicy;
use App\Services\Core\DocumentRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRetentionController extends Controller
{
    public function __construct(
        private readonly DocumentRetentionService $service
    ) {}

    // ---------------------------------------------------------------
    // Policies CRUD
    // ---------------------------------------------------------------

    /**
     * GET /retention/policies
     */
    public function index(Request $request): JsonResponse
    {
        $policies = RetentionPolicy::where('organization_id', $this->organizationId($request))
            ->orderBy('document_type')
            ->get();

        return $this->success($policies, 'Retention policies retrieved.');
    }

    /**
     * POST /retention/policies
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type'       => ['required', 'string', 'max:50'],
            'policy_name'         => ['required', 'string', 'max:100'],
            'retention_years'     => ['required', 'integer', 'min:1', 'max:100'],
            'jurisdiction'        => ['required', 'string', 'in:saudi_arabia,uae,india,global'],
            'action_on_expiry'    => ['required', 'string', 'in:archive,delete,notify_only'],
            'legal_hold_override' => ['boolean'],
            'is_active'           => ['boolean'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $policy = $this->service->storePolicy($validated);

        return $this->created($policy, 'Retention policy created.');
    }

    /**
     * GET /retention/policies/{retentionPolicy}
     */
    public function show(RetentionPolicy $retentionPolicy): JsonResponse
    {
        return $this->success($retentionPolicy, 'Retention policy retrieved.');
    }

    /**
     * PUT /retention/policies/{retentionPolicy}
     */
    public function update(Request $request, RetentionPolicy $retentionPolicy): JsonResponse
    {
        $validated = $request->validate([
            'policy_name'         => ['sometimes', 'string', 'max:100'],
            'retention_years'     => ['sometimes', 'integer', 'min:1', 'max:100'],
            'jurisdiction'        => ['sometimes', 'string', 'in:saudi_arabia,uae,india,global'],
            'action_on_expiry'    => ['sometimes', 'string', 'in:archive,delete,notify_only'],
            'legal_hold_override' => ['sometimes', 'boolean'],
            'is_active'           => ['sometimes', 'boolean'],
        ]);

        $policy = $this->service->updatePolicy($retentionPolicy, $validated);

        return $this->success($policy, 'Retention policy updated.');
    }

    /**
     * DELETE /retention/policies/{retentionPolicy}
     */
    public function destroy(RetentionPolicy $retentionPolicy): JsonResponse
    {
        $retentionPolicy->delete();

        return $this->success(null, 'Retention policy deleted.');
    }

    // ---------------------------------------------------------------
    // Legal Holds
    // ---------------------------------------------------------------

    /**
     * GET /retention/legal-holds
     */
    public function legalHoldsIndex(Request $request): JsonResponse
    {
        $holds = DocumentLegalHold::where('organization_id', $this->organizationId($request))
            ->where('is_active', true)
            ->with('heldByUser')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->success($holds, 'Legal holds retrieved.');
    }

    /**
     * POST /retention/legal-holds
     */
    public function placeLegalHold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'document_id'   => ['required', 'integer', 'min:1'],
            'hold_reason'   => ['required', 'string', 'max:500'],
            'hold_until'    => ['nullable', 'date', 'after:today'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $hold = $this->service->placeLegalHold($validated);

        return $this->created($hold, 'Legal hold placed.');
    }

    /**
     * DELETE /retention/legal-holds/{documentLegalHold}
     */
    public function releaseLegalHold(DocumentLegalHold $documentLegalHold): JsonResponse
    {
        $this->service->releaseLegalHold($documentLegalHold);

        return $this->success(null, 'Legal hold released.');
    }

    // ---------------------------------------------------------------
    // Schedule runner
    // ---------------------------------------------------------------

    /**
     * POST /retention/run
     */
    public function runSchedule(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $run   = $this->service->runRetentionSchedule($orgId);

        return $this->success($run, 'Retention schedule completed.');
    }

    // ---------------------------------------------------------------
    // Expiring documents preview
    // ---------------------------------------------------------------

    /**
     * GET /retention/expiring?within_days=90
     */
    public function expiringDocuments(Request $request): JsonResponse
    {
        $request->validate([
            'within_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $orgId      = $this->organizationId($request);
        $withinDays = $request->integer('within_days', 90);
        $expiring   = $this->service->getExpiringDocuments($orgId, $withinDays);

        return $this->success([
            'within_days' => $withinDays,
            'total'       => count($expiring),
            'documents'   => $expiring,
        ], 'Expiring documents preview retrieved.');
    }
}
