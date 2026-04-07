<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\ServiceTicket;
use App\Models\CRM\SlaPolicy;
use App\Services\CRM\ServiceTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceTicketController extends Controller
{
    public function __construct(
        private ServiceTicketService $ticketService
    ) {}

    /**
     * List service tickets with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ServiceTicket::with(['contact', 'assignedTo', 'slaPolicy', 'creator'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->assigned_to, fn($q, $v) => $q->where('assigned_to', $v))
            ->when($request->contact_id, fn($q, $v) => $q->where('contact_id', $v))
            ->when($request->sla_breached === 'true', fn($q) => $q->breached())
            ->when($request->overdue === 'true', fn($q) => $q->overdue())
            ->when($request->search, fn($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            }))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['ticket_number', 'status', 'priority', 'created_at', 'resolution_due_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $tickets = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($tickets);
    }

    /**
     * Create a new service ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'subject'        => 'required|string|max:255',
            'description'    => 'required|string',
            'priority'       => ['nullable', Rule::in(array_keys($this->priorityOptions()))],
            'type'           => ['nullable', Rule::in(array_keys($this->typeOptions()))],
            'source'         => ['nullable', Rule::in(array_keys($this->sourceOptions()))],
            'contact_id'     => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'assigned_to'    => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'sla_policy_id'  => ['nullable', Rule::exists('sla_policies', 'id')->where('organization_id', $orgId)],
            'branch_id'      => ['nullable', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
        ]);

        $validated['organization_id'] = $orgId;

        $ticket = $this->ticketService->create($validated, auth()->id());

        return $this->success($ticket->load(['contact', 'assignedTo', 'slaPolicy']), 'Service ticket created.', 201);
    }

    /**
     * Show a single service ticket.
     */
    public function show(ServiceTicket $serviceTicket): JsonResponse
    {
        $serviceTicket->load(['contact', 'assignedTo', 'slaPolicy', 'comments.user', 'creator']);

        return $this->success($serviceTicket);
    }

    /**
     * Update a service ticket.
     */
    public function update(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'subject'        => 'sometimes|required|string|max:255',
            'description'    => 'sometimes|required|string',
            'status'         => ['sometimes', Rule::in(array_keys($this->statusOptions()))],
            'priority'       => ['sometimes', Rule::in(array_keys($this->priorityOptions()))],
            'type'           => ['sometimes', Rule::in(array_keys($this->typeOptions()))],
            'source'         => ['sometimes', Rule::in(array_keys($this->sourceOptions()))],
            'contact_id'     => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'assigned_to'    => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'sla_policy_id'  => ['nullable', Rule::exists('sla_policies', 'id')->where('organization_id', $orgId)],
            'customer_rating'   => 'nullable|integer|min:1|max:5',
            'customer_feedback' => 'nullable|string|max:1000',
        ]);

        $serviceTicket->update($validated);

        return $this->success($serviceTicket->fresh(['contact', 'assignedTo', 'slaPolicy']), 'Ticket updated.');
    }

    /**
     * Assign a ticket to an agent.
     */
    public function assign(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $orgId = auth()->user()->organization_id;

        $validated = $request->validate([
            'agent_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $orgId)],
        ]);

        $ticket = $this->ticketService->assign($serviceTicket, $validated['agent_id']);

        return $this->success($ticket->load(['assignedTo']), 'Ticket assigned.');
    }

    /**
     * Add a comment to a ticket.
     */
    public function addComment(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $validated = $request->validate([
            'body'        => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $comment = $this->ticketService->addComment(
            $serviceTicket,
            $validated['body'],
            auth()->id(),
            (bool) ($validated['is_internal'] ?? false)
        );

        return $this->success($comment->load('user'), 'Comment added.', 201);
    }

    /**
     * Record first response.
     */
    public function recordFirstResponse(ServiceTicket $serviceTicket): JsonResponse
    {
        $ticket = $this->ticketService->recordFirstResponse($serviceTicket, auth()->id());

        return $this->success($ticket, 'First response recorded.');
    }

    /**
     * Resolve a ticket.
     */
    public function resolve(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $ticket = $this->ticketService->resolve($serviceTicket, $validated['resolution_notes'], auth()->id());

        return $this->success($ticket, 'Ticket resolved.');
    }

    /**
     * Close a ticket.
     */
    public function close(ServiceTicket $serviceTicket): JsonResponse
    {
        $ticket = $this->ticketService->close($serviceTicket, auth()->id());

        return $this->success($ticket, 'Ticket closed.');
    }

    // --- SLA Policy CRUD ---

    /**
     * List SLA policies.
     */
    public function indexSla(Request $request): JsonResponse
    {
        $policies = SlaPolicy::when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->when($request->active === 'true', fn($q) => $q->active())
            ->orderBy('priority')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($policies);
    }

    /**
     * Create a new SLA policy.
     */
    public function storeSla(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:200',
            'description'           => 'nullable|string',
            'priority'              => ['required', Rule::in(array_keys($this->priorityOptions()))],
            'first_response_hours'  => 'required|integer|min:1',
            'resolution_hours'      => 'required|integer|min:1',
            'business_hours_only'   => 'boolean',
            'is_active'             => 'boolean',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $policy = SlaPolicy::create($validated);

        return $this->success($policy, 'SLA policy created.', 201);
    }

    // Option helpers
    private function statusOptions(): array
    {
        return [
            ServiceTicket::STATUS_OPEN             => 'Open',
            ServiceTicket::STATUS_IN_PROGRESS      => 'In Progress',
            ServiceTicket::STATUS_PENDING_CUSTOMER => 'Pending Customer',
            ServiceTicket::STATUS_RESOLVED         => 'Resolved',
            ServiceTicket::STATUS_CLOSED           => 'Closed',
            ServiceTicket::STATUS_CANCELLED        => 'Cancelled',
        ];
    }

    private function priorityOptions(): array
    {
        return [
            ServiceTicket::PRIORITY_LOW      => 'Low',
            ServiceTicket::PRIORITY_MEDIUM   => 'Medium',
            ServiceTicket::PRIORITY_HIGH     => 'High',
            ServiceTicket::PRIORITY_CRITICAL => 'Critical',
        ];
    }

    private function typeOptions(): array
    {
        return [
            ServiceTicket::TYPE_BUG             => 'Bug',
            ServiceTicket::TYPE_FEATURE_REQUEST => 'Feature Request',
            ServiceTicket::TYPE_BILLING         => 'Billing',
            ServiceTicket::TYPE_TECHNICAL       => 'Technical',
            ServiceTicket::TYPE_GENERAL         => 'General',
        ];
    }

    private function sourceOptions(): array
    {
        return [
            ServiceTicket::SOURCE_EMAIL  => 'Email',
            ServiceTicket::SOURCE_PHONE  => 'Phone',
            ServiceTicket::SOURCE_PORTAL => 'Portal',
            ServiceTicket::SOURCE_CHAT   => 'Chat',
            ServiceTicket::SOURCE_MANUAL => 'Manual',
        ];
    }
}
