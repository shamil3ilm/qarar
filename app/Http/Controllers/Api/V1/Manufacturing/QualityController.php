<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\DefectRecord;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\QualityNotification;
use App\Models\Manufacturing\QualityPlan;
use App\Services\Manufacturing\QualityManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityController extends Controller
{
    public function __construct(
        private QualityManagementService $qualityService,
    ) {}

    // =========================================================================
    // Quality Plans
    // =========================================================================

    /**
     * List quality plans with optional filters.
     */
    public function indexPlans(Request $request): JsonResponse
    {
        $query = QualityPlan::with(['product', 'productCategory'])
            ->withCount('characteristics')
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->inspection_stage, fn ($q, $stage) => $q->forStage($stage))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['name', 'inspection_stage', 'created_at', 'updated_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $plans = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($plans);
    }

    /**
     * Store a new quality plan.
     */
    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'product_id' => 'nullable|exists:products,id',
            'product_category_id' => 'nullable|exists:categories,id',
            'inspection_stage' => 'nullable|in:goods_receipt,production,pre_shipment,in_process,final',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
            'characteristics' => 'nullable|array',
            'characteristics.*.name' => 'required_with:characteristics|string|max:255',
            'characteristics.*.description' => 'nullable|string',
            'characteristics.*.inspection_method' => 'nullable|string|max:255',
            'characteristics.*.measurement_unit' => 'nullable|string|max:100',
            'characteristics.*.lower_limit' => 'nullable|numeric',
            'characteristics.*.upper_limit' => 'nullable|numeric|gte:characteristics.*.lower_limit',
            'characteristics.*.target_value' => 'nullable|numeric',
            'characteristics.*.is_mandatory' => 'nullable|boolean',
            'characteristics.*.sort_order' => 'nullable|integer|min:0',
        ]);

        $characteristics = $validated['characteristics'] ?? [];
        unset($validated['characteristics']);

        $plan = $this->qualityService->createQualityPlan(
            $validated,
            $characteristics,
            auth()->id()
        );

        return $this->created($plan->load(['characteristics', 'product']));
    }

    /**
     * Show a single quality plan with its characteristics.
     */
    public function showPlan(int $id): JsonResponse
    {
        $plan = QualityPlan::with(['characteristics', 'product', 'productCategory', 'creator'])
            ->find($id);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        return $this->success($plan);
    }

    /**
     * Update a quality plan (plan fields only; characteristics managed separately).
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = QualityPlan::find($id);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'product_id' => 'nullable|exists:products,id',
            'product_category_id' => 'nullable|exists:categories,id',
            'inspection_stage' => 'nullable|in:goods_receipt,production,pre_shipment,in_process,final',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $plan->update($validated);

        return $this->success($plan->fresh(['characteristics', 'product']));
    }

    /**
     * Soft-delete a quality plan.
     */
    public function destroyPlan(int $id): JsonResponse
    {
        $plan = QualityPlan::find($id);

        if ($plan === null) {
            return $this->notFound('Quality plan not found.');
        }

        $plan->delete();

        return $this->success(null, 'Quality plan deleted successfully.');
    }

    // =========================================================================
    // Inspection Lots
    // =========================================================================

    /**
     * List inspection lots with optional filters.
     */
    public function indexLots(Request $request): JsonResponse
    {
        $query = InspectionLot::with(['product', 'warehouse', 'qualityPlan', 'inspector'])
            ->withCount('results')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->source_type, fn ($q, $type) => $q->where('source_type', $type))
            ->when($request->search, function ($q, $search) {
                $q->where('lot_number', 'like', "%{$search}%");
            })
            ->when($request->from, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['lot_number', 'status', 'created_at', 'inspection_date'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $lots = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($lots);
    }

    /**
     * Create a new inspection lot.
     */
    public function storeLot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quality_plan_id' => 'nullable|exists:quality_plans,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'source_type' => 'nullable|in:purchase_order,production,transfer,manual',
            'source_id' => 'nullable|integer',
            'quantity' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string',
        ]);

        $lot = $this->qualityService->createInspectionLot($validated, auth()->id());

        return $this->created($lot);
    }

    /**
     * Show a single inspection lot with results.
     */
    public function showLot(int $id): JsonResponse
    {
        $lot = InspectionLot::with([
            'qualityPlan.characteristics',
            'product',
            'warehouse',
            'inspector',
            'creator',
            'results.recorder',
            'results.characteristic',
        ])->find($id);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        return $this->success($lot);
    }

    /**
     * Record inspection results against an existing lot.
     */
    public function recordResults(Request $request, int $id): JsonResponse
    {
        $lot = InspectionLot::find($id);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        if ($lot->isAccepted() || $lot->isRejected()) {
            return $this->error('Inspection lot is already completed.', 422);
        }

        $validated = $request->validate([
            'results' => 'required|array|min:1',
            'results.*.quality_plan_characteristic_id' => 'nullable|exists:quality_plan_characteristics,id',
            'results.*.characteristic_name' => 'required_without:results.*.quality_plan_characteristic_id|string|max:255',
            'results.*.measured_value' => 'nullable|numeric',
            'results.*.text_result' => 'nullable|string|max:500',
            'results.*.is_conforming' => 'nullable|boolean',
            'results.*.notes' => 'nullable|string',
        ]);

        $lot = $this->qualityService->recordInspectionResults(
            $lot,
            $validated['results'],
            auth()->id()
        );

        return $this->success($lot->load(['results.recorder', 'results.characteristic']));
    }

    /**
     * Complete an inspection lot by providing accepted and rejected quantities.
     */
    public function completeLot(Request $request, int $id): JsonResponse
    {
        $lot = InspectionLot::find($id);

        if ($lot === null) {
            return $this->notFound('Inspection lot not found.');
        }

        $validated = $request->validate([
            'accepted_quantity' => 'required|numeric|min:0',
            'rejected_quantity' => 'required|numeric|min:0',
        ]);

        $lot = $this->qualityService->completeInspection(
            $lot,
            (float) $validated['accepted_quantity'],
            (float) $validated['rejected_quantity'],
            auth()->id()
        );

        return $this->success($lot->fresh(['product', 'inspector']));
    }

    // =========================================================================
    // Quality Notifications
    // =========================================================================

    /**
     * List quality notifications with optional filters.
     */
    public function indexNotifications(Request $request): JsonResponse
    {
        $query = QualityNotification::with(['product', 'assignee', 'creator'])
            ->withCount('defects')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->notification_type, fn ($q, $type) => $q->where('notification_type', $type))
            ->when($request->assigned_to, fn ($q, $id) => $q->where('assigned_to', $id))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->overdue === 'true', fn ($q) => $q->overdue())
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('notification_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->from, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->to, fn ($q, $to) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['notification_number', 'priority', 'status', 'due_date', 'created_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $notifications = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($notifications);
    }

    /**
     * Create a new quality notification.
     */
    public function storeNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_type' => 'nullable|in:defect,complaint,improvement,deviation',
            'source_type' => 'nullable|in:inspection_lot,customer,supplier,internal',
            'source_id' => 'nullable|integer',
            'product_id' => 'nullable|exists:products,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'defects' => 'nullable|array',
            'defects.*.defect_type' => 'required_with:defects|string|max:100',
            'defects.*.defect_code' => 'nullable|string|max:50',
            'defects.*.quantity' => 'nullable|integer|min:1',
            'defects.*.severity' => 'nullable|in:minor,major,critical',
            'defects.*.description' => 'nullable|string',
            'defects.*.location' => 'nullable|string|max:255',
        ]);

        $notification = $this->qualityService->createNotification($validated, auth()->id());

        return $this->created($notification);
    }

    /**
     * Show a single quality notification with defects.
     */
    public function showNotification(int $id): JsonResponse
    {
        $notification = QualityNotification::with([
            'defects',
            'product',
            'assignee',
            'creator',
            'resolver',
        ])->find($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        return $this->success($notification);
    }

    /**
     * Assign a notification to a user.
     */
    public function assignNotification(Request $request, int $id): JsonResponse
    {
        $notification = QualityNotification::find($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $notification = $this->qualityService->assignNotification(
            $notification,
            (int) $validated['assigned_to'],
            auth()->id()
        );

        return $this->success($notification);
    }

    /**
     * Resolve a quality notification.
     */
    public function resolveNotification(Request $request, int $id): JsonResponse
    {
        $notification = QualityNotification::find($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $validated = $request->validate([
            'root_cause' => 'required|string',
            'corrective_action' => 'required|string',
            'preventive_action' => 'nullable|string',
        ]);

        $notification = $this->qualityService->resolveNotification(
            $notification,
            $validated,
            auth()->id()
        );

        return $this->success($notification);
    }

    /**
     * Close a resolved notification.
     */
    public function closeNotification(int $id): JsonResponse
    {
        $notification = QualityNotification::find($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $notification = $this->qualityService->closeNotification($notification, auth()->id());

        return $this->success($notification);
    }

    /**
     * List defect records for a notification.
     */
    public function listDefects(int $id): JsonResponse
    {
        $notification = QualityNotification::find($id);

        if ($notification === null) {
            return $this->notFound('Quality notification not found.');
        }

        $defects = DefectRecord::where('quality_notification_id', $notification->id)
            ->orderBy('created_at')
            ->get();

        return $this->success($defects);
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Return quality management statistics for the authenticated organisation.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $orgId = auth()->user()->organization_id;

        $stats = $this->qualityService->getQualityStats(
            $orgId,
            $validated['from'],
            $validated['to']
        );

        return $this->success($stats);
    }

}
