<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\PlatformAdmin;
use App\Models\Admin\SupportTicket;
use App\Models\Admin\SupportTicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketService
{
    /**
     * Create a new support ticket.
     */
    public function create(array $data): SupportTicket
    {
        return DB::transaction(function () use ($data) {
            $ticket = SupportTicket::create([
                'ticket_number' => $this->generateTicketNumber(),
                'organization_id' => $data['organization_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'assigned_admin_id' => $data['assigned_admin_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $data['category'] ?? SupportTicket::CATEGORY_GENERAL,
                'priority' => $data['priority'] ?? SupportTicket::PRIORITY_MEDIUM,
                'status' => SupportTicket::STATUS_OPEN,
                'tags' => $data['tags'] ?? null,
                'source' => $data['source'] ?? SupportTicket::SOURCE_WEB,
            ]);

            if (!empty($data['message'])) {
                SupportTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $data['user_id'] ?? null,
                    'admin_id' => $data['admin_id'] ?? null,
                    'message' => $data['message'],
                    'is_internal_note' => false,
                ]);
            }

            return $ticket->load('messages');
        });
    }

    /**
     * Reply to a support ticket.
     */
    public function reply(SupportTicket $ticket, array $data): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $data) {
            $message = SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $data['user_id'] ?? null,
                'admin_id' => $data['admin_id'] ?? null,
                'message' => $data['message'],
                'is_internal_note' => $data['is_internal_note'] ?? false,
                'attachments' => $data['attachments'] ?? null,
            ]);

            if ($data['admin_id'] ?? null) {
                if (!$ticket->first_response_at) {
                    $ticket->first_response_at = now();
                }
                $ticket->status = SupportTicket::STATUS_WAITING_RESPONSE;
            } else {
                $ticket->status = SupportTicket::STATUS_IN_PROGRESS;
            }

            $ticket->save();

            return $message;
        });
    }

    /**
     * Assign a ticket to an admin.
     */
    public function assign(SupportTicket $ticket, int $adminId): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $adminId) {
            $ticket->update([
                'assigned_admin_id' => $adminId,
                'status' => $ticket->status === SupportTicket::STATUS_OPEN
                    ? SupportTicket::STATUS_IN_PROGRESS
                    : $ticket->status,
            ]);

            return $ticket->fresh('assignedAdmin');
        });
    }

    /**
     * Resolve a support ticket.
     */
    public function resolve(SupportTicket $ticket, ?string $resolutionNote = null): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $resolutionNote) {
            $ticket->update([
                'status' => SupportTicket::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);

            if ($resolutionNote) {
                SupportTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'admin_id' => $ticket->assigned_admin_id,
                    'message' => $resolutionNote,
                    'is_internal_note' => false,
                ]);
            }

            return $ticket->fresh();
        });
    }

    /**
     * Close a support ticket.
     */
    public function close(SupportTicket $ticket, ?array $satisfaction = null): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $satisfaction) {
            $updateData = [
                'status' => SupportTicket::STATUS_CLOSED,
                'closed_at' => now(),
            ];

            if ($satisfaction) {
                $updateData['satisfaction_rating'] = $satisfaction['rating'] ?? null;
                $updateData['satisfaction_feedback'] = $satisfaction['feedback'] ?? null;
            }

            $ticket->update($updateData);

            return $ticket->fresh();
        });
    }

    /**
     * Escalate a ticket priority.
     */
    public function escalate(SupportTicket $ticket, string $priority, ?string $reason = null): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $priority, $reason) {
            $ticket->update(['priority' => $priority]);

            if ($reason) {
                SupportTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'admin_id' => $ticket->assigned_admin_id,
                    'message' => "Ticket escalated to {$priority}: {$reason}",
                    'is_internal_note' => true,
                ]);
            }

            return $ticket->fresh();
        });
    }

    /**
     * Generate a unique ticket number.
     */
    private function generateTicketNumber(): string
    {
        $prefix = 'TKT';
        $datePart = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$datePart}-{$random}";
    }
}
