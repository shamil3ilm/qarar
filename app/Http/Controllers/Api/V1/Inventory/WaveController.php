<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\PickingList;
use App\Models\Inventory\PickingListLine;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\WavePlan;
use App\Services\Inventory\WaveManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaveController extends Controller
{
    public function __construct(
        private WaveManagementService $waveService
    ) {}

    // -------------------------------------------------------------------------
    // Putaway Rules
    // -------------------------------------------------------------------------

    /**
     * List putaway rules for the authenticated organisation.
     */
    public function putawayIndex(Request $request): JsonResponse
    {
        $query = PutawayRule::with(['warehouse', 'product', 'productCategory', 'preferredLocation'])
            ->orderBy('priority')
            ->when($request->has('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('active_only'), fn($q) => $q->active());

        $rules = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($rules);
    }

    /**
     * Create a putaway rule.
     */
    public function putawayStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => 'required|integer|exists:inventory_warehouses,id',
            'product_id'            => 'nullable|integer|exists:inventory_products,id',
            'product_category_id'   => 'nullable|integer|exists:inventory_categories,id',
            'warehouse_zone'        => 'nullable|string|max:100',
            'preferred_location_id' => 'nullable|integer|exists:inventory_warehouse_locations,id',
            'priority'              => 'sometimes|integer|min:1|max:255',
            'is_active'             => 'sometimes|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $rule = $this->waveService->createPutawayRule($validated, $request->user()->id);

        return $this->created($rule->load(['warehouse', 'product', 'productCategory', 'preferredLocation']));
    }

    /**
     * Update a putaway rule.
     */
    public function putawayUpdate(Request $request, int $id): JsonResponse
    {
        $rule = PutawayRule::findOrFail($id);

        $validated = $request->validate([
            'warehouse_id'          => 'sometimes|integer|exists:inventory_warehouses,id',
            'product_id'            => 'nullable|integer|exists:inventory_products,id',
            'product_category_id'   => 'nullable|integer|exists:inventory_categories,id',
            'warehouse_zone'        => 'nullable|string|max:100',
            'preferred_location_id' => 'nullable|integer|exists:inventory_warehouse_locations,id',
            'priority'              => 'sometimes|integer|min:1|max:255',
            'is_active'             => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return $this->success($rule->load(['warehouse', 'product', 'productCategory', 'preferredLocation']));
    }

    /**
     * Delete a putaway rule.
     */
    public function putawayDestroy(int $id): JsonResponse
    {
        $rule = PutawayRule::findOrFail($id);
        $rule->delete();

        return $this->success([], 'Putaway rule deleted successfully.');
    }

    /**
     * Suggest a putaway location for a product.
     */
    public function putawaySuggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'  => 'required|integer|exists:inventory_warehouses,id',
            'product_id'    => 'required|integer|exists:inventory_products,id',
            'category_id'   => 'required|integer|exists:inventory_categories,id',
        ]);

        $location = $this->waveService->getPutawayLocation(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['category_id'],
        );

        if ($location === null) {
            return $this->success(null, 'No matching putaway rule found.');
        }

        return $this->success($location);
    }

    // -------------------------------------------------------------------------
    // Wave Plans
    // -------------------------------------------------------------------------

    /**
     * List wave plans.
     */
    public function waveIndex(Request $request): JsonResponse
    {
        $query = WavePlan::with(['warehouse', 'creator'])
            ->latest()
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('warehouse_id'), fn($q) => $q->forWarehouse($request->integer('warehouse_id')))
            ->when($request->has('wave_type'), fn($q) => $q->where('wave_type', $request->input('wave_type')))
            ->when($request->has('from_date'), fn($q) => $q->where('planned_date', '>=', $request->input('from_date')))
            ->when($request->has('to_date'), fn($q) => $q->where('planned_date', '<=', $request->input('to_date')));

        $waves = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($waves);
    }

    /**
     * Create a wave plan.
     */
    public function waveStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'      => 'required|integer|exists:inventory_warehouses,id',
            'wave_number'       => 'nullable|string|max:50',
            'wave_type'         => 'sometimes|in:outbound,replenishment,returns',
            'planned_date'      => 'required|date',
            'orders'            => 'required|array|min:1',
            'orders.*.order_type' => 'required|in:sales_order,stock_transfer,purchase_return',
            'orders.*.order_id'   => 'required|integer|min:1',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $wave = $this->waveService->createWavePlan(
            data: collect($validated)->except('orders')->all(),
            orderIds: $validated['orders'],
            userId: $request->user()->id,
        );

        return $this->created($wave->load(['warehouse', 'waveOrders']));
    }

    /**
     * Show a wave plan.
     */
    public function waveShow(int $id): JsonResponse
    {
        $wave = WavePlan::with(['warehouse', 'waveOrders', 'pickingLists.lines', 'creator'])
            ->findOrFail($id);

        return $this->success($wave);
    }

    /**
     * Release a wave plan and generate picking lists.
     */
    public function waveRelease(Request $request, int $id): JsonResponse
    {
        $wave = WavePlan::findOrFail($id);
        $wave = $this->waveService->releaseWave($wave, $request->user()->id);

        return $this->success($wave->load(['pickingLists.lines']), 'Wave plan released successfully.');
    }

    /**
     * Complete a wave plan.
     */
    public function waveComplete(Request $request, int $id): JsonResponse
    {
        $wave = WavePlan::findOrFail($id);

        if ($wave->isCompleted()) {
            return $this->success($wave, 'Wave plan is already completed.');
        }

        $wave->complete($request->user()->id);

        return $this->success($wave->refresh(), 'Wave plan completed successfully.');
    }

    // -------------------------------------------------------------------------
    // Picking Lists
    // -------------------------------------------------------------------------

    /**
     * List picking lists.
     */
    public function pickingListIndex(Request $request): JsonResponse
    {
        $query = PickingList::with(['wave', 'warehouse', 'picker'])
            ->latest()
            ->when($request->has('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->has('warehouse_id'), fn($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->has('picker_id'), fn($q) => $q->forPicker($request->integer('picker_id')))
            ->when($request->has('wave_id'), fn($q) => $q->where('wave_plan_id', $request->integer('wave_id')));

        $lists = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($lists);
    }

    /**
     * Show a picking list with its lines.
     */
    public function pickingListShow(int $id): JsonResponse
    {
        $list = PickingList::with([
            'wave',
            'warehouse',
            'picker',
            'lines' => fn($q) => $q->orderBy('sort_order'),
            'lines.product',
            'lines.variant',
            'lines.fromLocation',
            'lines.toLocation',
        ])->findOrFail($id);

        return $this->success($list);
    }

    /**
     * Assign a picker to a picking list.
     */
    public function pickingListAssign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'picker_id' => 'required|integer|exists:users,id',
        ]);

        $list = PickingList::findOrFail($id);
        $list = $this->waveService->assignPicker($list, $validated['picker_id'], $request->user()->id);

        return $this->success($list->load('picker'), 'Picker assigned successfully.');
    }

    /**
     * Start a picking list.
     */
    public function pickingListStart(Request $request, int $id): JsonResponse
    {
        $list = PickingList::findOrFail($id);
        $list = $this->waveService->startPicking($list, $request->user()->id);

        return $this->success($list, 'Picking started.');
    }

    /**
     * Complete a picking list.
     */
    public function pickingListComplete(Request $request, int $id): JsonResponse
    {
        $list = PickingList::findOrFail($id);
        $list = $this->waveService->completePicking($list, $request->user()->id);

        return $this->success($list, 'Picking list completed.');
    }

    /**
     * Pick a line (record picked quantity).
     */
    public function pickLine(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'notes'    => 'nullable|string|max:500',
        ]);

        $line = PickingListLine::findOrFail($id);

        if (!empty($validated['notes'])) {
            $line->notes = $validated['notes'];
            $line->save();
        }

        $line = $this->waveService->pickLine($line, (float) $validated['quantity'], $request->user()->id);

        return $this->success(
            $line->load(['product', 'fromLocation', 'toLocation']),
            'Pick recorded successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Get wave & picking statistics for the organisation.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $stats = $this->waveService->getWaveStats(
            $this->organizationId($request),
            $validated['from'],
            $validated['to'],
        );

        return $this->success($stats);
    }
}
