<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\ServiceTicket;
use App\Models\CRM\ServiceTicketComment;
use App\Models\CRM\SlaPolicy;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceTicketService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Create a new service ticket, applying SLA deadlines if a policy is attached.
     */
    public function create(array $data, int $userId): ServiceTicket
    {
        return DB::transaction(function () use ($data, $userId): ServiceTicket {
            if (empty($data['ticket_number'])) {
                $data['ticket_number'] = $this->numberGenerator->generate('TKT');
            }

            $data['status'] = $data['status'] ?? ServiceTicket::STATUS_OPEN;
            $data['priority'] = $data['priority'] ?? ServiceTicket::PRIORITY_MEDIUM;
            $data['type'] = $data['type'] ?? ServiceTicket::TYPE_GENERAL;
            $data['source'] = $data['source'] ?? ServiceTicket::SOURCE_MANUAL;
            $data['created_by'] = $userId;

            $ticket = ServiceTicket::create($data);

            // Apply SLA deadlines once the ticket (with timestamps) exists
            if ($ticket->sla_policy_id) {
                $ticket->load('slaPolicy');
                $deadlines = $ticket->slaPolicy->calculateDeadlines($ticket->created_at);
                $ticket->update([
                    'first_response_due_at' => $deadlines['first_response_due_at'],
                    'resolution_due_at'     => $deadlines['resolution_due_at'],
                ]);
            }

            return $ticket->fresh(['contact', 'assignedTo', 'slaPolicy']);
        });
    }

    /**
     * Assign the ticket to a support agent.
     */
    public function assign(ServiceTicket $ticket, int $agentId): ServiceTicket
    {
        return DB::transaction(function () use ($ticket, $agentId): ServiceTicket {
            $ticket->update([
                'assigned_to' => $agentId,
                'status'      => $ticket->status === ServiceTicket::STATUS_OPEN
                    ? ServiceTicket::STATUS_IN_PROGRESS
                    : $ticket->status,
            ]);

            return $ticket->fresh();
        });
    }

    /**
     * Add a comment to the ticket.
     */
    public function addComment(
        ServiceTicket $ticket,
        string $body,
        int $userId,
        bool $isInternal = false
    ): ServiceTicketComment {
        if (empty(trim($body))) {
            throw new InvalidArgumentException('Comment body cannot be empty.');
        }

        return ServiceTicketComment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $userId,
            'body'        => $body,
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Record that the first response has been sent.
     * Checks whether the first-response SLA was breached.
     */
    public function recordFirstResponse(ServiceTicket $ticket, int $userId): ServiceTicket
    {
        if ($ticket->first_response_at) {
            throw new InvalidArgumentException('First response has already been recorded for this ticket.');
        }

        return DB::transaction(function () use ($ticket, $userId): ServiceTicket {
            $now = now();
            $slaBreached = $ticket->sla_breached;

            if ($ticket->first_response_due_at && $now->gt($ticket->first_response_due_at)) {
                $slaBreached = true;
            }

            $ticket->update([
                'first_response_at' => $now,
                'sla_breached'      => $slaBreached,
                'status'            => $ticket->status === ServiceTicket::STATUS_OPEN
                    ? ServiceTicket::STATUS_IN_PROGRESS
                    : $ticket->status,
            ]);

            // Leave an internal note
            $this->addComment(
                $ticket,
                "First response recorded by user #{$userId}.",
                $userId,
                true
            );

            return $ticket->fresh();
        });
    }

    /**
     * Resolve the ticket with resolution notes.
     */
    public function resolve(ServiceTicket $ticket, string $notes, int $userId): ServiceTicket
    {
        if ($ticket->isResolved() || $ticket->isClosed()) {
            throw new InvalidArgumentException('Ticket is already resolved or closed.');
        }

        if (empty(trim($notes))) {
            throw new InvalidArgumentException('Resolution notes are required.');
        }

        return DB::transaction(function () use ($ticket, $notes, $userId): ServiceTicket {
            $now = now();
            $slaBreached = $ticket->sla_breached;

            if ($ticket->resolution_due_at && $now->gt($ticket->resolution_due_at)) {
                $slaBreached = true;
            }

            $ticket->update([
                'status'           => ServiceTicket::STATUS_RESOLVED,
                'resolved_at'      => $now,
                'resolution_notes' => $notes,
                'sla_breached'     => $slaBreached,
            ]);

            $this->addComment(
                $ticket,
                "Ticket resolved by user #{$userId}. Notes: {$notes}",
                $userId,
                true
            );

            return $ticket->fresh();
        });
    }

    /**
     * Close a resolved ticket.
     */
    public function close(ServiceTicket $ticket, int $userId): ServiceTicket
    {
        if ($ticket->isClosed()) {
            throw new InvalidArgumentException('Ticket is already closed.');
        }

        return DB::transaction(function () use ($ticket, $userId): ServiceTicket {
            $ticket->update([
                'status'    => ServiceTicket::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $this->addComment(
                $ticket,
                "Ticket closed by user #{$userId}.",
                $userId,
                true
            );

            return $ticket->fresh();
        });
    }

    /**
     * Scan for unbreached tickets where SLA deadlines have now passed.
     * Should be called from a scheduled command.
     *
     * @return int Number of tickets newly marked as SLA breached
     */
    public function checkSlaBreaches(): int
    {
        $count = 0;

        // Find active tickets with overdue resolution SLA not yet marked as breached
        ServiceTicket::active()
            ->where('sla_breached', false)
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    // First response SLA breached
                    $q->whereNotNull('first_response_due_at')
                        ->whereNull('first_response_at')
                        ->where('first_response_due_at', '<', now());
                })->orWhere(function ($q): void {
                    // Resolution SLA breached
                    $q->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', now());
                });
            })
            ->each(function (ServiceTicket $ticket) use (&$count): void {
                $ticket->update(['sla_breached' => true]);
                $count++;
            });

        return $count;
    }

    /**
     * Update SLA policy on an existing ticket and recalculate deadlines.
     */
    public function applySlaPolicy(ServiceTicket $ticket, int $slaPolicyId): ServiceTicket
    {
        $policy = SlaPolicy::findOrFail($slaPolicyId);

        $deadlines = $policy->calculateDeadlines($ticket->created_at);

        $ticket->update([
            'sla_policy_id'         => $slaPolicyId,
            'first_response_due_at' => $deadlines['first_response_due_at'],
            'resolution_due_at'     => $deadlines['resolution_due_at'],
        ]);

        return $ticket->fresh();
    }
}
