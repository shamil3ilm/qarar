<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SupportTicket;
use App\Models\Admin\SupportTicketMessage;
use App\Services\Admin\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));
        return $this->paginated($tickets);
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return $this->success($ticket->load('messages'));
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'message' => $request->input('message'),
            'sender_type' => 'admin',
            'sender_id' => auth()->id(),
        ]);
        return $this->created($message);
    }

    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket->update([
            'assigned_to' => $request->input('admin_id'),
            'status' => 'in_progress',
        ]);
        return $this->success($ticket->fresh());
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        return $this->success($ticket->fresh());
    }

    public function reopen(SupportTicket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'open', 'resolved_at' => null]);
        return $this->success($ticket->fresh());
    }

    public function stats(): JsonResponse
    {
        return $this->success([
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
        ]);
    }
}
