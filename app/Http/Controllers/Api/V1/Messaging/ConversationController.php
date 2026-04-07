<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\ConversationMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * List conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $organizationId = auth()->user()->organization_id;

        $query = Conversation::with(['participants:id,name', 'latestMessage'])
            ->where('organization_id', $organizationId)
            ->whereHas('participants', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('left_at');
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        $conversations = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($conversations);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = auth()->user()->organization_id;

        // Validate all participants belong to same organization
        $participantIds = $validated['participant_ids'];
        $validParticipants = User::where('organization_id', $organizationId)
            ->whereIn('id', $participantIds)
            ->pluck('id')
            ->toArray();

        if (count($validParticipants) !== count($participantIds)) {
            return $this->error(
                'One or more participants do not belong to this organization',
                'INVALID_PARTICIPANTS',
                422
            );
        }

        $conversation = DB::transaction(function () use ($validated, $organizationId, $participantIds) {
            $type = count($participantIds) > 1 ? 'group' : 'direct';

            $conversation = Conversation::create([
                'organization_id' => $organizationId,
                'subject' => $validated['subject'] ?? null,
                'type' => $type,
                'created_by' => auth()->id(),
            ]);

            // Add creator as participant
            $allParticipants = array_unique(array_merge([auth()->id()], $participantIds));
            foreach ($allParticipants as $participantId) {
                $conversation->participants()->attach($participantId, [
                    'joined_at' => now(),
                ]);
            }

            return $conversation->load('participants:id,name');
        });

        return $this->created($conversation, 'Conversation created successfully');
    }

    /**
     * Show a specific conversation.
     */
    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::with(['participants:id,name'])
            ->where('organization_id', auth()->user()->organization_id)
            ->whereHas('participants', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->find($id);

        if (!$conversation) {
            return $this->notFound('Conversation not found');
        }

        return $this->success($conversation);
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $conversation = Conversation::where('organization_id', auth()->user()->organization_id)
            ->whereHas('participants', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->find($conversationId);

        if (!$conversation) {
            return $this->notFound('Conversation not found');
        }

        $messages = ConversationMessage::with('sender:id,name')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($messages);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:text,file,image'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = Conversation::where('organization_id', auth()->user()->organization_id)
            ->whereHas('participants', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->find($conversationId);

        if (!$conversation) {
            return $this->notFound('Conversation not found');
        }

        $message = DB::transaction(function () use ($conversation, $validated) {
            $message = ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => auth()->id(),
                'content' => $validated['content'],
                'type' => $validated['type'] ?? 'text',
                'file_url' => $validated['file_url'] ?? null,
                'file_name' => $validated['file_name'] ?? null,
                'file_size' => $validated['file_size'] ?? null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message->load('sender:id,name');
        });

        return $this->created($message, 'Message sent successfully');
    }

    /**
     * Mark a conversation as read for the authenticated user.
     */
    public function markAsRead(int $conversationId): JsonResponse
    {
        $conversation = Conversation::where('organization_id', auth()->user()->organization_id)
            ->whereHas('participants', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->find($conversationId);

        if (!$conversation) {
            return $this->notFound('Conversation not found');
        }

        $conversation->markAsRead(auth()->id());

        return $this->success(null, 'Conversation marked as read');
    }
}
