<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\CapacityRequirement;
use App\Models\Manufacturing\WorkCenter;
use App\Services\Manufacturing\CapacityPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CapacityController extends Controller
{
    public function __construct(
        private CapacityPlanningService $capacityService
    ) {}

    // -------------------------------------------------------------------------
    // Work Centers — CRUD
    // -------------------------------------------------------------------------

    /**
     * List work centers.
     */
    public function indexWorkCenters(Request $request): JsonResponse
    {
        $query = WorkCenter::with(['createdBy'])
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->type, fn($q, $t) => $q->ofType($t))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['code', 'name', 'created_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            );

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }

    /**
     * Store a new work center.
     */
    public function storeWorkCenter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'               => [
                'required', 'string', 'max:50',
                Rule::unique('work_centers')->where('organization_id', auth()->user()->organization_id),
            ],
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'work_center_type'   => 'nullable|in:machine,labor,assembly,inspection,other',
            'capacity_per_day'   => 'nullable|numeric|min:0|max:24',
            'efficiency_percent' => 'nullable|numeric|min:1|max:200',
            'calendar_type'      => 'nullable|in:5day,6day,7day',
            'cost_per_hour'      => 'nullable|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'is_active'          => 'nullable|boolean',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $workCenter = $this->capacityService->createWorkCenter($validated, auth()->id());

        return $this->created($workCenter, 'Work center created successfully.');
    }

    /**
     * Show a single work center.
     */
    public function showWorkCenter(int $id): JsonResponse
    {
        $workCenter = WorkCenter::with(['exceptions', 'createdBy'])->find($id);

        if ($workCenter === null) {
            return $this->notFound('Work center not found.');
        }

        return $this->success($workCenter);
    }

    /**
     * Update a work center.
     */
    public function updateWorkCenter(Request $request, int $id): JsonResponse
    {
        $workCenter = WorkCenter::find($id);

        if ($workCenter === null) {
            return $this->notFound('Work center not found.');
        }

        $validated = $request->validate([
            'code'               => [
                'sometimes', 'string', 'max:50',
                Rule::unique('work_centers')
                    ->where('organization_id', auth()->user()->organization_id)
                    ->ignore($workCenter->id),
            ],
            'name'               => 'sometimes|string|max:255',
            'description'        => 'nullable|string',
            'work_center_type'   => 'sometimes|in:machine,labor,assembly,inspection,other',
            'capacity_per_day'   => 'sometimes|numeric|min:0|max:24',
            'efficiency_percent' => 'sometimes|numeric|min:1|max:200',
            'calendar_type'      => 'sometimes|in:5day,6day,7day',
            'cost_per_hour'      => 'nullable|numeric|min:0',
            'currency_code'      => 'sometimes|string|size:3',
            'is_active'          => 'sometimes|boolean',
        ]);

        $updated = $this->capacityService->updateWorkCenter($workCenter, $validated, auth()->id());

        return $this->success($updated, 'Work center updated successfully.');
    }

    /**
     * Soft-delete a work center.
     */
    public function destroyWorkCenter(int $id): JsonResponse
    {
        $workCenter = WorkCenter::find($id);

        if ($workCenter === null) {
            return $this->notFound('Work center not found.');
        }

        $workCenter->delete();

        return $this->success(null, 'Work center deleted successfully.');
    }

    /**
     * Get capacity load for a specific work center over a date range.
     */
    public function workCenterLoad(Request $request, int $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $validated['from'] ?? now()->toDateString();
        $to   = $validated['to']   ?? now()->addDays(30)->toDateString();

        $data = $this->capacityService->getCapacityLoad(
            auth()->user()->organization_id,
            $from,
            $to,
            $workCenter
        );

        return $this->success($data);
    }

    // -------------------------------------------------------------------------
    // Work Center Exceptions
    // -------------------------------------------------------------------------

    /**
     * Add or update a calendar exception for a work center.
     */
    public function storeException(Request $request, int $id): JsonResponse
    {
        $workCenter = WorkCenter::find($id);

        if ($workCenter === null) {
            return $this->notFound('Work center not found.');
        }

        $validated = $request->validate([
            'exception_date'  => 'required|date',
            'available_hours' => 'required|numeric|min:0|max:24',
            'reason'          => 'nullable|string|max:255',
        ]);

        $exception = $this->capacityService->setException(
            $workCenter,
            $validated['exception_date'],
            (float) $validated['available_hours'],
            $validated['reason'] ?? '',
            auth()->id()
        );

        return $this->created($exception, 'Exception saved successfully.');
    }

    // -------------------------------------------------------------------------
    // Work Order Capacity actions
    // -------------------------------------------------------------------------

    /**
     * Plan capacity requirements for a work order.
     */
    public function planCapacity(int $workOrderId): JsonResponse
    {
        $requirements = $this->capacityService->planCapacity($workOrderId, auth()->id());

        return $this->created([
            'requirements' => $requirements,
            'count'        => count($requirements),
        ], 'Capacity planned successfully.');
    }

    /**
     * Reschedule a work order to a new start date.
     */
    public function reschedule(Request $request, int $workOrderId): JsonResponse
    {
        $validated = $request->validate([
            'new_start_date' => 'required|date',
        ]);

        $requirements = $this->capacityService->rescheduleOrder(
            $workOrderId,
            $validated['new_start_date'],
            auth()->id()
        );

        return $this->success([
            'requirements' => $requirements,
            'count'        => count($requirements),
        ], 'Work order rescheduled successfully.');
    }

    /**
     * Release capacity for a work order (cancel requirements).
     */
    public function releaseCapacity(int $workOrderId): JsonResponse
    {
        $this->capacityService->releaseCapacity($workOrderId, auth()->id());

        return $this->success(null, 'Capacity released successfully.');
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    /**
     * Get capacity load for a date range.
     */
    public function capacityLoad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'           => 'required|date',
            'to'             => 'required|date|after_or_equal:from|before_or_equal:' . now()->parse($request->input('from', now()))->addDays(90)->toDateString(),
            'work_center_id' => 'nullable|integer|exists:work_centers,id',
        ]);

        $data = $this->capacityService->getCapacityLoad(
            auth()->user()->organization_id,
            $validated['from'],
            $validated['to'],
            isset($validated['work_center_id']) ? (int) $validated['work_center_id'] : null
        );

        return $this->success($data);
    }

    /**
     * Detect bottleneck work centers (>90% utilisation).
     */
    public function bottlenecks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $data = $this->capacityService->detectBottlenecks(
            auth()->user()->organization_id,
            $validated['from'],
            $validated['to']
        );

        return $this->success($data);
    }

    /**
     * List capacity requirements (with filters).
     */
    public function requirements(Request $request): JsonResponse
    {
        $query = CapacityRequirement::with(['workCenter', 'workOrder'])
            ->when($request->work_center_id, fn($q, $id) => $q->where('work_center_id', $id))
            ->when($request->work_order_id, fn($q, $id) => $q->where('work_order_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('scheduled_start');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 15)),
            null
        );
    }
}
