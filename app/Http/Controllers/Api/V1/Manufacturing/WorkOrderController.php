<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Manufacturing\WorkOrderResource;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Services\Manufacturing\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function __construct(
        private WorkOrderService $workOrderService
    ) {
    }

    /**
     * List work orders with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with(['product', 'bomTemplate', 'assignedTo', 'sourceWarehouse', 'targetWarehouse'])
            ->withCount(['materials', 'operations', 'productionLogs'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn($q, $priority) => $q->where('priority', $priority))
            ->when($request->product_id, fn($q, $id) => $q->forProduct($id))
            ->when($request->assigned_to, fn($q, $id) => $q->assignedTo($id))
            ->when($request->branch_id, fn($q, $id) => $q->where('branch_id', $id))
            ->when($request->overdue === 'true', fn($q) => $q->overdue())
            ->when($request->active === 'true', fn($q) => $q->active())
            ->when($request->start_date, fn($q, $date) => $q->where('planned_start_date', '>=', $date))
            ->when($request->end_date, fn($q, $date) => $q->where('planned_end_date', '<=', $date))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('work_order_number', 'like', "%{$search}%")
                        ->orWhereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['order_number', 'status', 'start_date', 'end_date', 'created_at', 'updated_at'], 'created_at'),
                $this->safeSortOrder($request->sort_order, 'desc')
            );

        $workOrders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($workOrders, WorkOrderResource::class);
    }

    /**
     * Store a new work order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bom_template_id' => ['required', Rule::exists('bom_templates', 'id')->where('organization_id', auth()->user()->organization_id)],
            'planned_quantity' => 'required|numeric|min:0.0001',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'branch_id' => 'nullable|exists:branches,id',
            'source_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'target_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'sales_order_id' => 'nullable|integer',
            'sales_order_line_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        // Default planned_end_date if not provided
        if (empty($validated['planned_end_date'])) {
            $validated['planned_end_date'] = $validated['planned_start_date'];
        }

        $bom = BomTemplate::withoutGlobalScope('organization')->find($validated['bom_template_id']);

        if (!$bom) {
            return $this->error('BOM template not found.', 'NOT_FOUND', 404);
        }

        // Validate BOM belongs to the user's organization
        if ($bom->organization_id !== auth()->user()->organization_id) {
            return $this->error('BOM template does not belong to your organization.', 'VALIDATION_ERROR', 422);
        }

        try {
            $workOrder = $this->workOrderService->create($bom, $validated, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new WorkOrderResource($workOrder), 'Work order created successfully.');
    }

    /**
     * Show a specific work order.
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        return $this->success(new WorkOrderResource(
            $workOrder->load([
                'product',
                'variant',
                'bomTemplate',
                'unit',
                'sourceWarehouse',
                'targetWarehouse',
                'assignedTo',
                'supervisor',
                'branch',
                'materials.product',
                'materials.unit',
                'materials.warehouse',
                'operations.assignedTo',
                'operations.completedBy',
                'productionLogs.loggedBy',
                'createdBy',
            ])
        ));
    }

    /**
     * Update a work order.
     */
    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'planned_start_date' => 'sometimes|date',
            'planned_end_date' => 'sometimes|date|after_or_equal:planned_start_date',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'source_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'target_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->update($workOrder, $validated)),
            'Work order updated successfully.'
        );
    }

    /**
     * Delete a draft work order.
     */
    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        if (!$workOrder->isDraft()) {
            return $this->error('Only draft work orders can be deleted.', 'VALIDATION_ERROR', 422);
        }

        $workOrder->materials()->delete();
        $workOrder->operations()->delete();
        $workOrder->delete();

        return $this->success(null, 'Work order deleted successfully.');
    }

    /**
     * Release work order for production.
     */
    public function release(WorkOrder $workOrder): JsonResponse
    {
        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->release($workOrder)),
            'Work order released successfully.'
        );
    }

    /**
     * Schedule a work order.
     */
    public function schedule(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->schedule($workOrder, $validated)),
            'Work order scheduled successfully.'
        );
    }

    /**
     * Start a work order.
     */
    public function start(WorkOrder $workOrder): JsonResponse
    {
        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->start($workOrder)),
            'Work order started successfully.'
        );
    }

    /**
     * Complete a work order.
     */
    public function complete(WorkOrder $workOrder): JsonResponse
    {
        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->complete($workOrder)),
            'Work order completed successfully.'
        );
    }

    /**
     * Cancel a work order.
     */
    public function cancel(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason'              => 'nullable|string|max:500',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $reason = $validated['cancellation_reason'] ?? $validated['reason'] ?? '';

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->cancel($workOrder, $reason)),
            'Work order cancelled successfully.',
        );
    }

    /**
     * Issue materials to work order.
     */
    public function issueMaterials(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'issues' => 'required|array|min:1',
            'issues.*.work_order_material_id' => 'required|exists:work_order_materials,id',
            'issues.*.quantity' => 'required|numeric|min:0.0001',
            'issues.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'issues.*.reference' => 'nullable|string|max:100',
            'issues.*.notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->issueMaterials($workOrder, $validated['issues'], auth()->id())),
            'Materials issued successfully.'
        );
    }

    /**
     * Return materials from work order.
     */
    public function returnMaterials(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'returns' => 'required|array|min:1',
            'returns.*.work_order_material_id' => 'required|exists:work_order_materials,id',
            'returns.*.quantity' => 'required|numeric|min:0.0001',
            'returns.*.warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('organization_id', auth()->user()->organization_id)],
            'returns.*.reference' => 'nullable|string|max:100',
            'returns.*.notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->returnMaterials($workOrder, $validated['returns'], auth()->id())),
            'Materials returned successfully.'
        );
    }

    /**
     * Record material consumption.
     */
    public function consumeMaterials(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'consumptions' => 'required|array|min:1',
            'consumptions.*.work_order_material_id' => 'required|exists:work_order_materials,id',
            'consumptions.*.quantity' => 'required|numeric|min:0.0001',
            'consumptions.*.wastage_quantity' => 'nullable|numeric|min:0',
            'consumptions.*.wastage_reason' => 'nullable|string|max:500',
        ]);

        return $this->tryAction(
            fn() => new WorkOrderResource($this->workOrderService->consumeMaterials($workOrder, $validated['consumptions'], auth()->id())),
            'Material consumption recorded successfully.'
        );
    }

    /**
     * Record production output.
     */
    public function recordProduction(Request $request, WorkOrder $workOrder): JsonResponse
    {
        // Support both field name conventions from tests
        $validated = $request->validate([
            'quantity_produced' => 'nullable|numeric|min:0.0001',
            'good_quantity' => 'nullable|numeric|min:0.0001',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'rejected_quantity' => 'nullable|numeric|min:0',
            'rejection_reason' => 'nullable|string|max:500',
            'batch_number' => 'nullable|string|max:100',
            'lot_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'logged_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Map alternate field names
        $goodQuantity = $validated['good_quantity'] ?? null;
        $rejectedQuantity = $validated['rejected_quantity'] ?? $validated['quantity_rejected'] ?? 0;

        if ($goodQuantity !== null && !isset($validated['quantity_produced'])) {
            // Test sends good_quantity + rejected_quantity, service expects quantity_produced = total
            $validated['quantity_produced'] = (float) $goodQuantity + (float) $rejectedQuantity;
            $validated['quantity_rejected'] = $rejectedQuantity;
        }

        if (empty($validated['quantity_produced']) || $validated['quantity_produced'] <= 0) {
            return $this->error('A valid production quantity is required.', 'VALIDATION_ERROR', 422);
        }

        try {
            $log = $this->workOrderService->recordProduction($workOrder, $validated, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success(new WorkOrderResource($workOrder->fresh()), 'Production recorded successfully.');
    }

    /**
     * Start an operation.
     */
    public function startOperation(WorkOrder $workOrder, WorkOrderOperation $operation): JsonResponse
    {
        if ($operation->work_order_id !== $workOrder->id) {
            return $this->error('Operation does not belong to this work order.', 'VALIDATION_ERROR', 422);
        }

        if (!$operation->canBeStarted()) {
            return $this->error('Operation cannot be started in its current status.', 'VALIDATION_ERROR', 422);
        }

        $operation->start();

        return $this->success(new WorkOrderResource($workOrder->fresh(['operations'])), 'Operation started successfully.');
    }

    /**
     * Complete an operation.
     */
    public function completeOperation(Request $request, WorkOrder $workOrder, WorkOrderOperation $operation): JsonResponse
    {
        if ($operation->work_order_id !== $workOrder->id) {
            return $this->error('Operation does not belong to this work order.', 'VALIDATION_ERROR', 422);
        }

        if (!$operation->canBeCompleted()) {
            return $this->error('Operation cannot be completed in its current status.', 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'actual_minutes' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $operation->complete(
            $validated['actual_minutes'] ?? null,
            $validated['notes'] ?? null
        );

        return $this->success(new WorkOrderResource($workOrder->fresh(['operations'])), 'Operation completed successfully.');
    }

    /**
     * Get work order statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = $request->only(['branch_id', 'start_date', 'end_date']);

        $statistics = $this->workOrderService->getStatistics($filters);

        return $this->success($statistics);
    }

    /**
     * Get production schedule.
     */
    public function productionSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $schedule = $this->workOrderService->getProductionSchedule(
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->success($schedule);
    }
}
