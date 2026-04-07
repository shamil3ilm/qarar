<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Jobs\ReevaluateSegmentMembershipsJob;
use App\Models\Campaign\UserSegment;
use App\Services\Campaign\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $segments = UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($segments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions'  => 'required|array',
            'conditions.*.field'    => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value'    => 'required',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
            'is_dynamic' => 'nullable|boolean',
        ]);

        $organizationId = $this->organizationId($request);

        $segment = UserSegment::create([
            'organization_id' => $organizationId,
            'name'            => $validated['name'],
            'description'     => $validated['description'] ?? null,
            'conditions'      => $validated['conditions'],
            'color'           => $validated['color'] ?? '#6366f1',
            'is_dynamic'      => $validated['is_dynamic'] ?? true,
            'created_by'      => auth()->id(),
        ]);

        // Trigger async reevaluation for this segment
        dispatch(function () use ($segment) {
            app(SegmentService::class)->reevaluateSegment($segment);
        })->afterResponse();

        return $this->created($segment, 'Segment created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $segment = UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $members = $segment->members()
            ->paginate(request()->integer('per_page', 15));

        return $this->success([
            'segment' => $segment,
            'members' => [
                'data' => $members->items(),
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'per_page'     => $members->perPage(),
                    'total'        => $members->total(),
                    'last_page'    => $members->lastPage(),
                ],
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $segment = UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions'  => 'sometimes|array',
            'conditions.*.field'    => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string',
            'conditions.*.value'    => 'required_with:conditions',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
            'is_dynamic' => 'nullable|boolean',
        ]);

        $conditionsChanged = isset($validated['conditions'])
            && $validated['conditions'] !== $segment->conditions;

        $segment->update($validated);

        if ($conditionsChanged) {
            dispatch(function () use ($segment) {
                app(SegmentService::class)->reevaluateSegment($segment->fresh());
            })->afterResponse();
        }

        return $this->success($segment->fresh(), 'Segment updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $segment = UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $segment->delete();

        return $this->success(null, 'Segment deleted successfully.');
    }

    public function members(Request $request, string $id): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $segment = UserSegment::where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->firstOrFail();

        $members = $segment->members()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($members);
    }
}
