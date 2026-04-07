<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaigns = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->withCount('sends')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string|max:2000',
            'trigger_event'      => 'nullable|string|max:100',
            'conditions'         => 'nullable|array',
            'target_segment_id'  => 'nullable|integer|exists:user_segments,id',
            'actions'            => 'required|array|min:1',
            'actions.*.type'     => 'required|string|in:notification,sms,database',
            'schedule_type'      => 'nullable|string|in:immediate,delayed,scheduled',
            'delay_minutes'      => 'nullable|integer|min:1',
            'scheduled_at'       => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'max_sends_per_user' => 'nullable|integer|min:1',
        ]);

        $organizationId = $this->organizationId($request);

        $campaign = Campaign::create([
            'organization_id'    => $organizationId,
            'name'               => $validated['name'],
            'description'        => $validated['description'] ?? null,
            'trigger_event'      => $validated['trigger_event'] ?? null,
            'conditions'         => $validated['conditions'] ?? null,
            'target_segment_id'  => $validated['target_segment_id'] ?? null,
            'actions'            => $validated['actions'],
            'status'             => Campaign::STATUS_DRAFT,
            'schedule_type'      => $validated['schedule_type'] ?? 'immediate',
            'delay_minutes'      => $validated['delay_minutes'] ?? null,
            'scheduled_at'       => $validated['scheduled_at'] ?? null,
            'start_date'         => $validated['start_date'] ?? null,
            'end_date'           => $validated['end_date'] ?? null,
            'max_sends_per_user' => $validated['max_sends_per_user'] ?? 1,
            'created_by'         => auth()->id(),
        ]);

        return $this->created($campaign, 'Campaign created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaign = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->withCount('sends')
            ->where('id', $id)
            ->firstOrFail();

        return $this->success($campaign);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaign = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'description'        => 'nullable|string|max:2000',
            'trigger_event'      => 'nullable|string|max:100',
            'conditions'         => 'nullable|array',
            'target_segment_id'  => 'nullable|integer|exists:user_segments,id',
            'actions'            => 'sometimes|array|min:1',
            'actions.*.type'     => 'required_with:actions|string|in:notification,sms,database',
            'schedule_type'      => 'nullable|string|in:immediate,delayed,scheduled',
            'delay_minutes'      => 'nullable|integer|min:1',
            'scheduled_at'       => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'max_sends_per_user' => 'nullable|integer|min:1',
        ]);

        $campaign->update($validated);

        return $this->success($campaign->fresh(), 'Campaign updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaign = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $campaign->delete();

        return $this->success(null, 'Campaign deleted successfully.');
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaign = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $campaign->update(['status' => Campaign::STATUS_ACTIVE]);

        return $this->success($campaign->fresh(), 'Campaign activated successfully.');
    }

    public function pause(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $campaign = Campaign::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $campaign->update(['status' => Campaign::STATUS_PAUSED]);

        return $this->success($campaign->fresh(), 'Campaign paused successfully.');
    }
}
