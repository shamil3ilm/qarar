<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * List activities for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['creator:id,name', 'assignee:id,name'])
            ->when($request->activity_type, fn($q, $type) => $q->where('activity_type', $type))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn($q, $priority) => $q->where('priority', $priority))
            ->when($request->assigned_to, fn($q, $userId) => $q->where('assigned_to', $userId))
            ->when($request->related_type, function ($q) use ($request) {
                $q->where('related_type', $request->related_type);
                if ($request->related_id) {
                    $q->where('related_id', $request->related_id);
                }
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_datetime')
            ->orderByDesc('id');

        $activities = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($activities);
    }

    /**
     * Create a new activity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_type' => ['required', 'string', 'in:call,email,meeting,task,note,follow_up'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_all_day' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'call_direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'call_result' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'attendees' => ['nullable', 'array'],
            'reminder_datetime' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $activity = Activity::create([
            ...$validated,
            'organization_id' => $this->organizationId($request),
            'status' => Activity::STATUS_PLANNED,
            'created_by' => auth()->id(),
        ]);

        return $this->created($activity->load(['creator:id,name', 'assignee:id,name']), 'Activity created successfully');
    }

    /**
     * Show a specific activity.
     */
    public function show(int $id): JsonResponse
    {
        $activity = Activity::with(['creator:id,name', 'assignee:id,name'])
            ->where('organization_id', auth()->user()->organization_id)
            ->find($id);

        if (!$activity) {
            return $this->notFound('Activity not found');
        }

        return $this->success($activity);
    }

    /**
     * Update an activity.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $activity = Activity::where('organization_id', auth()->user()->organization_id)
            ->find($id);

        if (!$activity) {
            return $this->notFound('Activity not found');
        }

        if ($activity->isCompleted()) {
            return $this->error('Cannot update a completed activity', 'ACTIVITY_COMPLETED', 422);
        }

        if ($activity->isCancelled()) {
            return $this->error('Cannot update a cancelled activity', 'ACTIVITY_CANCELLED', 422);
        }

        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'attendees' => ['nullable', 'array'],
            'reminder_datetime' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $activity->update($validated);

        return $this->success($activity->fresh()->load(['creator:id,name', 'assignee:id,name']), 'Activity updated successfully');
    }

    /**
     * Complete an activity.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $activity = Activity::where('organization_id', auth()->user()->organization_id)
            ->find($id);

        if (!$activity) {
            return $this->notFound('Activity not found');
        }

        if ($activity->isCompleted()) {
            return $this->error('Activity is already completed', 'ALREADY_COMPLETED', 422);
        }

        if ($activity->isCancelled()) {
            return $this->error('Cannot complete a cancelled activity', 'ACTIVITY_CANCELLED', 422);
        }

        $validated = $request->validate([
            'outcome' => ['nullable', 'string'],
        ]);

        $activity->complete($validated['outcome'] ?? null);

        return $this->success($activity->fresh()->load(['creator:id,name', 'assignee:id,name']), 'Activity completed successfully');
    }
}
