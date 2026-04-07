<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CreditExposure;
use App\Models\Accounting\CreditHold;
use App\Models\Accounting\CreditLimit;
use App\Models\Sales\Contact;
use App\Services\Accounting\CreditManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditManagementController extends Controller
{
    public function __construct(
        private readonly CreditManagementService $creditService
    ) {}

    // -------------------------------------------------------------------------
    // Credit Limits
    // -------------------------------------------------------------------------

    public function indexLimits(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $limits = CreditLimit::where('organization_id', $organizationId)
            ->with(['contact', 'reviewer'])
            ->when($request->input('risk_class'), fn($q, $v) => $q->where('risk_class', $v))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($limits, null, 'Credit limits retrieved.');
    }

    public function storeLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'         => 'required|exists:contacts,id',
            'credit_limit'       => 'required|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'valid_from'         => 'required|date',
            'valid_until'        => 'nullable|date|after:valid_from',
            'payment_terms_days' => 'nullable|integer|min:0',
            'risk_class'         => 'nullable|in:low,medium,high,blocked',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $contact = Contact::findOrFail($validated['contact_id']);
        $limit   = $this->creditService->setCreditLimit($contact, $validated);

        return $this->success($limit->load(['contact', 'reviewer']), 'Credit limit saved.', 201);
    }

    public function updateLimit(Request $request, CreditLimit $creditLimit): JsonResponse
    {
        $validated = $request->validate([
            'credit_limit'       => 'sometimes|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'valid_from'         => 'sometimes|date',
            'valid_until'        => 'nullable|date',
            'payment_terms_days' => 'nullable|integer|min:0',
            'risk_class'         => 'nullable|in:low,medium,high,blocked',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $limit = $this->creditService->setCreditLimit($creditLimit->contact, array_merge(
            $creditLimit->toArray(),
            $validated
        ));

        return $this->success($limit->load(['contact', 'reviewer']), 'Credit limit updated.');
    }

    // -------------------------------------------------------------------------
    // Credit Exposure
    // -------------------------------------------------------------------------

    public function showExposure(Request $request, Contact $contact): JsonResponse
    {
        $exposure = $this->creditService->getCreditExposure($contact);

        return $this->success($exposure, 'Credit exposure retrieved.');
    }

    public function indexExposureSnapshots(Request $request, Contact $contact): JsonResponse
    {
        $snapshots = CreditExposure::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('snapshot_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($snapshots, null, 'Credit exposure snapshots retrieved.');
    }

    public function snapshotExposures(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'snapshot_date' => 'nullable|date',
        ]);

        $organization = $this->organization($request);
        $date         = isset($validated['snapshot_date'])
            ? \Illuminate\Support\Carbon::parse($validated['snapshot_date'])
            : now();

        $count = $this->creditService->snapshotExposures($organization, $date);

        return $this->success(['snapshotted' => $count], "Snapshotted {$count} customer exposures.");
    }

    // -------------------------------------------------------------------------
    // Credit Holds
    // -------------------------------------------------------------------------

    public function indexHolds(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $holds = CreditHold::where('organization_id', $organizationId)
            ->with(['contact', 'heldBy', 'releasedBy'])
            ->when($request->boolean('active_only', false), fn($q) => $q->active())
            ->orderByDesc('held_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($holds, null, 'Credit holds retrieved.');
    }

    public function placeHold(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'hold_reason' => 'required|string|max:500',
        ]);

        $hold = $this->creditService->placeHold($contact, $validated);

        return $this->success($hold->load(['contact', 'heldBy']), 'Credit hold placed.', 201);
    }

    public function releaseHold(Request $request, CreditHold $creditHold): JsonResponse
    {
        $validated = $request->validate([
            'release_reason' => 'required|string|max:500',
        ]);

        $hold = $this->creditService->releaseHold($creditHold, $validated['release_reason']);

        return $this->success($hold->load(['contact', 'releasedBy']), 'Credit hold released.');
    }
}
