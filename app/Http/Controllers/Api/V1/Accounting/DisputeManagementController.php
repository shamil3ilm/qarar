<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\DisputeCase;
use App\Services\Accounting\DisputeManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeManagementController extends Controller
{
    public function __construct(
        private DisputeManagementService $service
    ) {}

    /**
     * List dispute cases.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = $this->service->index([
            ...$request->only(['status', 'contact_id', 'dispute_reason', 'assigned_to', 'per_page']),
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->paginated($cases);
    }

    /**
     * Open a new dispute case.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type'   => ['required', 'in:invoice,payment_received,credit_note'],
            'document_id'     => ['required', 'integer', 'min:1'],
            'contact_id'      => ['required', 'integer', 'min:1'],
            'disputed_amount' => ['required', 'numeric', 'min:0.0001'],
            'dispute_reason'  => ['nullable', 'in:pricing,quality,quantity,delivery,duplicate,other'],
            'description'     => ['nullable', 'string'],
            'assigned_to'     => ['nullable', 'exists:users,id'],
            'due_date'        => ['nullable', 'date'],
        ]);

        $case = $this->service->openCase([
            ...$validated,
            'organization_id' => $this->organizationId($request),
            'created_by'      => auth()->id(),
        ]);

        return $this->created($case, 'Dispute case opened successfully.');
    }

    /**
     * Show a single dispute case.
     */
    public function show(DisputeCase $disputeCase): JsonResponse
    {
        $disputeCase->load(['assignedTo:id,name', 'createdBy:id,name']);

        return $this->success($disputeCase);
    }

    /**
     * Update a dispute case (status, assignee, notes).
     */
    public function update(Request $request, DisputeCase $disputeCase): JsonResponse
    {
        $validated = $request->validate([
            'status'         => ['nullable', 'in:open,in_review,escalated'],
            'assigned_to'    => ['nullable', 'exists:users,id'],
            'description'    => ['nullable', 'string'],
            'due_date'       => ['nullable', 'date'],
            'dispute_reason' => ['nullable', 'in:pricing,quality,quantity,delivery,duplicate,other'],
        ]);

        return $this->tryAction(
            fn() => $this->service->updateCase($disputeCase, $validated),
            'Dispute case updated.',
            'UPDATE_FAILED'
        );
    }

    /**
     * Resolve a dispute case.
     */
    public function resolve(Request $request, DisputeCase $disputeCase): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string'],
            'resolved_amount'  => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->tryAction(
            fn() => $this->service->resolve($disputeCase, $validated),
            'Dispute case resolved.',
            'RESOLVE_FAILED'
        );
    }

    /**
     * Close a resolved dispute case.
     */
    public function close(DisputeCase $disputeCase): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->close($disputeCase),
            'Dispute case closed.',
            'CLOSE_FAILED'
        );
    }

    /**
     * Return the collections worklist.
     */
    public function collectionsWorklist(Request $request): JsonResponse
    {
        $worklist = $this->service->getCollectionsWorklist([
            ...$request->only(['collections_status', 'assigned_to', 'min_overdue', 'per_page']),
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->paginated($worklist);
    }

    /**
     * Record a promise-to-pay for a contact.
     */
    public function promiseToPay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'          => ['required', 'integer', 'min:1'],
            'promise_to_pay_date' => ['required', 'date', 'after_or_equal:today'],
            'promise_amount'      => ['required', 'numeric', 'min:0.0001'],
            'assigned_to'         => ['nullable', 'exists:users,id'],
            'notes'               => ['nullable', 'string'],
        ]);

        $contactId = $validated['contact_id'];
        unset($validated['contact_id']);

        $entry = $this->service->recordPromiseToPay($contactId, [
            ...$validated,
            'organization_id' => $this->organizationId($request),
        ]);

        return $this->success($entry, 'Promise-to-pay recorded successfully.');
    }
}
