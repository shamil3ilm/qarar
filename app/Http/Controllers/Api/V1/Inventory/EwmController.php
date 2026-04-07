<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\EwmBin;
use App\Models\Inventory\EwmLaborTask;
use App\Models\Inventory\EwmStorageType;
use App\Models\Inventory\EwmTransferOrder;
use App\Services\Inventory\EwmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EwmController extends Controller
{
    public function __construct(private EwmService $ewmService) {}

    // =========================================================================
    // Storage Types
    // =========================================================================

    /**
     * List all storage types for a warehouse.
     * GET /inventory/ewm/storage-types?warehouse_id=
     */
    public function indexStorageTypes(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $orgId = $this->organizationId($request);
        $types = $this->ewmService->listStorageTypes($orgId, $request->integer('warehouse_id'));

        return $this->success($types, 'Storage types retrieved');
    }

    /**
     * Create a new storage type.
     * POST /inventory/ewm/storage-types
     */
    public function storeStorageType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => ['required', 'integer', 'exists:warehouses,id'],
            'code'                  => ['required', 'string', 'max:20'],
            'name'                  => ['required', 'string', 'max:100'],
            'type'                  => ['required', Rule::in([
                EwmStorageType::TYPE_BULK, EwmStorageType::TYPE_SHELVING, EwmStorageType::TYPE_HIGH_BAY,
                EwmStorageType::TYPE_PALLET, EwmStorageType::TYPE_FREEZER,
                EwmStorageType::TYPE_HAZMAT, EwmStorageType::TYPE_OPEN_STORAGE,
            ])],
            'putaway_strategy'      => ['sometimes', Rule::in([
                EwmStorageType::STRATEGY_FIFO, EwmStorageType::STRATEGY_FEFO, EwmStorageType::STRATEGY_LIFO,
                EwmStorageType::STRATEGY_NEAREST_BIN, EwmStorageType::STRATEGY_FIXED_BIN,
                EwmStorageType::STRATEGY_MAX_FILL, EwmStorageType::STRATEGY_OPEN,
            ])],
            'allow_partial_putaway' => ['sometimes', 'boolean'],
            'mixed_storage'         => ['sometimes', 'boolean'],
            'max_weight_kg'         => ['nullable', 'integer', 'min:1'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        $orgId      = $this->organizationId($request);
        $storageType = $this->ewmService->createStorageType($orgId, $validated);

        return $this->success($storageType, 'Storage type created', 201);
    }

    // =========================================================================
    // Bins
    // =========================================================================

    /**
     * List bins with optional filters.
     * GET /inventory/ewm/bins?warehouse_id=&storage_type_id=&status=
     */
    public function indexBins(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id'    => ['required', 'integer'],
            'storage_type_id' => ['nullable', 'integer'],
            'status'          => ['nullable', Rule::in([
                EwmBin::STATUS_ACTIVE, EwmBin::STATUS_BLOCKED,
                EwmBin::STATUS_INACTIVE, EwmBin::STATUS_RESERVED,
            ])],
            'aisle'           => ['nullable', 'string'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orgId   = $this->organizationId($request);
        $filters = $request->only(['warehouse_id', 'storage_type_id', 'status', 'aisle']);
        $bins    = $this->ewmService->listBins($orgId, $filters, $request->integer('per_page', 20));

        return $this->paginated($bins);
    }

    /**
     * Create a new bin.
     * POST /inventory/ewm/bins
     */
    public function storeBin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'      => ['required', 'integer', 'exists:warehouses,id'],
            'storage_type_id'   => ['required', 'integer', 'exists:ewm_storage_types,id'],
            'storage_section_id' => ['nullable', 'integer', 'exists:ewm_storage_sections,id'],
            'bin_code'          => ['required', 'string', 'max:50'],
            'aisle'             => ['nullable', 'string', 'max:10'],
            'row_number'        => ['nullable', 'string', 'max:10'],
            'column_number'     => ['nullable', 'string', 'max:10'],
            'level'             => ['nullable', 'string', 'max:10'],
            'max_weight_kg'     => ['nullable', 'numeric', 'min:0'],
            'max_volume_m3'     => ['nullable', 'numeric', 'min:0'],
            'status'            => ['sometimes', Rule::in([
                EwmBin::STATUS_ACTIVE, EwmBin::STATUS_BLOCKED,
                EwmBin::STATUS_INACTIVE, EwmBin::STATUS_RESERVED,
            ])],
            'mixed_products'    => ['sometimes', 'boolean'],
        ]);

        $orgId = $this->organizationId($request);
        $bin   = $this->ewmService->createBin($orgId, $validated);

        return $this->success($bin->load(['storageType', 'storageSection']), 'Bin created', 201);
    }

    /**
     * Show a single bin.
     * GET /inventory/ewm/bins/{uuid}
     */
    public function showBin(Request $request, string $uuid): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $bin = EwmBin::where('organization_id', $orgId)
            ->where('uuid', $uuid)
            ->with(['storageType', 'storageSection', 'currentProduct'])
            ->firstOrFail();

        return $this->success($bin, 'Bin retrieved');
    }

    /**
     * Update bin status (block/activate/reserve).
     * POST /inventory/ewm/bins/{uuid}/status
     */
    public function updateBinStatus(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EwmBin::STATUS_ACTIVE, EwmBin::STATUS_BLOCKED,
                EwmBin::STATUS_INACTIVE, EwmBin::STATUS_RESERVED,
            ])],
        ]);

        $orgId = $this->organizationId($request);
        $bin   = $this->ewmService->updateBinStatus($orgId, $uuid, $validated['status']);

        return $this->success($bin, 'Bin status updated');
    }

    /**
     * Suggest a putaway bin for a product/quantity combination.
     * GET /inventory/ewm/bins/putaway-suggestion?warehouse_id=&product_id=&qty=
     */
    public function findPutawayBin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'qty'          => ['required', 'numeric', 'min:0.0001'],
        ]);

        $orgId = $this->organizationId($request);
        $bin   = $this->ewmService->findPutawayBin(
            $orgId,
            (int) $validated['warehouse_id'],
            (int) $validated['product_id'],
            (float) $validated['qty']
        );

        if ($bin === null) {
            return $this->error('No suitable putaway bin found', 404);
        }

        return $this->success($bin->load(['storageType', 'storageSection']), 'Putaway bin suggested');
    }

    // =========================================================================
    // Transfer Orders
    // =========================================================================

    /**
     * List transfer orders.
     * GET /inventory/ewm/transfer-orders?warehouse_id=&status=&movement_type=
     */
    public function indexTransferOrders(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id'   => ['nullable', 'integer'],
            'status'         => ['nullable', Rule::in([
                EwmTransferOrder::STATUS_CREATED, EwmTransferOrder::STATUS_ASSIGNED,
                EwmTransferOrder::STATUS_IN_PROGRESS, EwmTransferOrder::STATUS_CONFIRMED,
                EwmTransferOrder::STATUS_CANCELLED,
            ])],
            'movement_type'  => ['nullable', Rule::in([
                EwmTransferOrder::MOVEMENT_GOODS_RECEIPT, EwmTransferOrder::MOVEMENT_GOODS_ISSUE,
                EwmTransferOrder::MOVEMENT_INTERNAL_MOVE, EwmTransferOrder::MOVEMENT_REPLENISHMENT,
                EwmTransferOrder::MOVEMENT_STOCK_TRANSFER, EwmTransferOrder::MOVEMENT_PHYSICAL_INVENTORY,
            ])],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orgId = $this->organizationId($request);

        $query = EwmTransferOrder::where('organization_id', $orgId)
            ->with(['sourceBin', 'destBin', 'product', 'assignedUser'])
            ->latest()
            ->when($request->filled('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('movement_type'), fn($q) => $q->where('movement_type', $request->input('movement_type')));

        $orders = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($orders);
    }

    /**
     * Create a new transfer order.
     * POST /inventory/ewm/transfer-orders
     */
    public function storeTransferOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'   => ['required', 'integer', 'exists:warehouses,id'],
            'movement_type'  => ['required', Rule::in([
                EwmTransferOrder::MOVEMENT_GOODS_RECEIPT, EwmTransferOrder::MOVEMENT_GOODS_ISSUE,
                EwmTransferOrder::MOVEMENT_INTERNAL_MOVE, EwmTransferOrder::MOVEMENT_REPLENISHMENT,
                EwmTransferOrder::MOVEMENT_STOCK_TRANSFER, EwmTransferOrder::MOVEMENT_PHYSICAL_INVENTORY,
            ])],
            'source_bin_id'   => ['nullable', 'integer', 'exists:ewm_bins,id'],
            'source_bin_code' => ['nullable', 'string', 'max:50'],
            'dest_bin_id'     => ['nullable', 'integer', 'exists:ewm_bins,id'],
            'dest_bin_code'   => ['nullable', 'string', 'max:50'],
            'product_id'      => ['required', 'integer', 'exists:products,id'],
            'requested_qty'   => ['required', 'numeric', 'min:0.0001'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'batch_number'    => ['nullable', 'string', 'max:50'],
            'serial_number'   => ['nullable', 'string', 'max:50'],
            'reference_type'  => ['nullable', 'string', 'max:50'],
            'reference_id'    => ['nullable', 'integer'],
            'priority'        => ['nullable', Rule::in([
                EwmLaborTask::PRIORITY_URGENT, EwmLaborTask::PRIORITY_HIGH,
                EwmLaborTask::PRIORITY_NORMAL, EwmLaborTask::PRIORITY_LOW,
            ])],
        ]);

        $orgId = $this->organizationId($request);
        $to    = $this->ewmService->createTransferOrder($orgId, $validated, auth()->id());

        return $this->success($to, 'Transfer order created', 201);
    }

    /**
     * Show a transfer order.
     * GET /inventory/ewm/transfer-orders/{uuid}
     */
    public function showTransferOrder(Request $request, string $uuid): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $to = EwmTransferOrder::where('organization_id', $orgId)
            ->where('uuid', $uuid)
            ->with(['sourceBin', 'destBin', 'product', 'assignedUser', 'createdBy', 'laborTasks'])
            ->firstOrFail();

        return $this->success($to, 'Transfer order retrieved');
    }

    /**
     * Confirm a transfer order and update bin fill levels.
     * POST /inventory/ewm/transfer-orders/{uuid}/confirm
     */
    public function confirmTransferOrder(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'confirmed_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $orgId = $this->organizationId($request);
        $to    = $this->ewmService->confirmTransferOrder(
            $orgId,
            $uuid,
            (float) $validated['confirmed_qty'],
            auth()->id()
        );

        return $this->success($to, 'Transfer order confirmed');
    }

    /**
     * Cancel a transfer order.
     * POST /inventory/ewm/transfer-orders/{uuid}/cancel
     */
    public function cancelTransferOrder(Request $request, string $uuid): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $to    = $this->ewmService->cancelTransferOrder($orgId, $uuid, auth()->id());

        return $this->success($to, 'Transfer order cancelled');
    }

    // =========================================================================
    // Labor Management
    // =========================================================================

    /**
     * Labor productivity dashboard.
     * GET /inventory/ewm/labor/dashboard?warehouse_id=&days=7
     */
    public function laborDashboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'days'         => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $orgId = $this->organizationId($request);
        $data  = $this->ewmService->getLaborDashboard(
            $orgId,
            (int) $validated['warehouse_id'],
            (int) ($validated['days'] ?? 7)
        );

        return $this->success($data, 'Labor dashboard retrieved');
    }

    /**
     * Assign a labor task to a warehouse worker.
     * POST /inventory/ewm/labor/tasks/{uuid}/assign
     */
    public function assignTask(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $orgId = $this->organizationId($request);
        $task  = $this->ewmService->assignTask($orgId, $uuid, (int) $validated['user_id']);

        return $this->success($task, 'Task assigned');
    }

    /**
     * Mark a labor task as started.
     * POST /inventory/ewm/labor/tasks/{uuid}/start
     */
    public function startTask(Request $request, string $uuid): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $task  = $this->ewmService->startTask($orgId, $uuid);

        return $this->success($task, 'Task started');
    }

    // =========================================================================
    // Reports & Config
    // =========================================================================

    /**
     * Bin utilization report.
     * GET /inventory/ewm/bin-utilization?warehouse_id=
     */
    public function binUtilization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $orgId = $this->organizationId($request);
        $data  = $this->ewmService->getBinUtilization($orgId, (int) $validated['warehouse_id']);

        return $this->success($data, 'Bin utilization retrieved');
    }

    /**
     * List putaway rules for a warehouse.
     * GET /inventory/ewm/putaway-rules?warehouse_id=
     */
    public function putawayRules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $orgId = $this->organizationId($request);
        $rules = $this->ewmService->listPutawayRules($orgId, (int) $validated['warehouse_id']);

        return $this->success($rules, 'Putaway rules retrieved');
    }

    /**
     * Create a new putaway rule.
     * POST /inventory/ewm/putaway-rules
     */
    public function storePutawayRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'    => ['required', 'integer', 'exists:warehouses,id'],
            'storage_type_id' => ['nullable', 'integer', 'exists:ewm_storage_types,id'],
            'product_id'      => ['nullable', 'integer', 'exists:products,id'],
            'category_id'     => ['nullable', 'integer', 'exists:categories,id'],
            'priority'        => ['nullable', 'integer', 'min:1', 'max:999'],
            'strategy'        => ['required', Rule::in([
                'fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill',
            ])],
            'fixed_bin_code'  => ['nullable', 'string', 'max:50', 'required_if:strategy,fixed_bin'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $orgId = $this->organizationId($request);
        $rule  = $this->ewmService->createPutawayRule($orgId, $validated);

        return $this->success($rule, 'Putaway rule created', 201);
    }
}
