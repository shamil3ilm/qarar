<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\WorkCenter;
use App\Services\Manufacturing\CapacityPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkCenterController extends Controller
{
    public function __construct(
        private CapacityPlanningService $capacityService,
    ) {}

    /**
     * List work centers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkCenter::with(['exceptions'])
            ->when($request->type, fn ($q, $t) => $q->ofType($t))
            ->when($request->active === 'true', fn ($q) => $q->active())
            ->when($request->search, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['code', 'name', 'work_center_type', 'created_at'], 'code'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        $workCenters = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($workCenters, fn ($wc) => $wc);
    }

    /**
     * Create a new work center.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'               => ['required', 'string', 'max:20'],
            'name'               => ['required', 'string', 'max:100'],
            'work_center_type'   => ['nullable', 'string', 'in:machine,labor,assembly,inspection,other'],
            'capacity_per_day'   => ['nullable', 'numeric', 'min:0'],
            'efficiency_percent' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'calendar_type'      => ['nullable', 'string', 'in:5day,6day,7day'],
            'cost_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'currency_code'      => ['nullable', 'string', 'max:3'],
            'is_active'          => ['nullable', 'boolean'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $workCenter = $this->capacityService->createWorkCenter($validated, auth()->id());

        return $this->created($workCenter);
    }

    /**
     * Show a single work center.
     */
    public function show(WorkCenter $workCenter): JsonResponse
    {
        $workCenter->load(['exceptions', 'loads', 'capacityRequirements']);

        return $this->success($workCenter);
    }

    /**
     * Update a work center.
     */
    public function update(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'code'               => ['nullable', 'string', 'max:20'],
            'name'               => ['nullable', 'string', 'max:100'],
            'work_center_type'   => ['nullable', 'string', 'in:machine,labor,assembly,inspection,other'],
            'capacity_per_day'   => ['nullable', 'numeric', 'min:0'],
            'efficiency_percent' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'calendar_type'      => ['nullable', 'string', 'in:5day,6day,7day'],
            'cost_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'currency_code'      => ['nullable', 'string', 'max:3'],
            'is_active'          => ['nullable', 'boolean'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->capacityService->updateWorkCenter($workCenter, $validated, auth()->id());

        return $this->success($updated);
    }

    /**
     * Soft-delete a work center.
     */
    public function destroy(WorkCenter $workCenter): JsonResponse
    {
        $workCenter->delete();

        return $this->success(null, 'Work center deleted successfully.');
    }
}
