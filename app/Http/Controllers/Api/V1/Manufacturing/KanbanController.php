<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\KanbanCard;
use App\Models\Manufacturing\KanbanControlCycle;
use App\Models\Manufacturing\KanbanSupplyArea;
use App\Services\Manufacturing\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanController extends Controller
{
    public function __construct(
        private KanbanService $kanbanService
    ) {}

    // ── Supply Areas ──────────────────────────────────────────────────────────

    /**
     * GET kanban/supply-areas
     */
    public function indexSupplyAreas(Request $request): JsonResponse
    {
        $areas = KanbanSupplyArea::with(['warehouse', 'location'])
            ->when($request->search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            }))
            ->orderBy('code')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($areas, null);
    }

    /**
     * POST kanban/supply-areas
     */
    public function storeSupplyArea(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'code'        => [
                'required', 'string', 'max:20',
                Rule::unique('kanban_supply_areas')->where('organization_id', $orgId),
            ],
            'name'        => 'required|string|max:100',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'location_id'  => 'nullable|integer|exists:warehouse_locations,id',
        ]);

        $area = KanbanSupplyArea::create(array_merge($validated, ['organization_id' => $orgId]));

        return $this->success($area->load(['warehouse', 'location']), 'Supply area created.', 201);
    }

    /**
     * GET kanban/supply-areas/{supplyArea}
     */
    public function showSupplyArea(KanbanSupplyArea $supplyArea): JsonResponse
    {
        return $this->success($supplyArea->load(['warehouse', 'location', 'controlCycles.product']));
    }

    /**
     * PUT kanban/supply-areas/{supplyArea}
     */
    public function updateSupplyArea(Request $request, KanbanSupplyArea $supplyArea): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'code'        => [
                'sometimes', 'string', 'max:20',
                Rule::unique('kanban_supply_areas')->where('organization_id', $orgId)->ignore($supplyArea->id),
            ],
            'name'        => 'sometimes|string|max:100',
            'warehouse_id' => 'sometimes|integer|exists:warehouses,id',
            'location_id'  => 'nullable|integer|exists:warehouse_locations,id',
        ]);

        $supplyArea->update($validated);

        return $this->success($supplyArea->fresh(['warehouse', 'location']), 'Supply area updated.');
    }

    /**
     * DELETE kanban/supply-areas/{supplyArea}
     */
    public function destroySupplyArea(KanbanSupplyArea $supplyArea): JsonResponse
    {
        $supplyArea->delete();

        return $this->success(null, 'Supply area deleted.');
    }

    // ── Control Cycles ────────────────────────────────────────────────────────

    /**
     * GET kanban/control-cycles
     */
    public function indexControlCycles(Request $request): JsonResponse
    {
        $cycles = KanbanControlCycle::with(['product', 'supplyArea'])
            ->withCount('cards')
            ->when($request->boolean('active_only', true), fn($q) => $q->active())
            ->when($request->product_id, fn($q, $id) => $q->where('product_id', $id))
            ->when($request->supply_area_id, fn($q, $id) => $q->where('supply_area_id', $id))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($cycles, null);
    }

    /**
     * POST kanban/control-cycles
     */
    public function storeControlCycle(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'product_id'                   => 'required|integer|exists:products,id',
            'supply_area_id'               => 'required|integer|exists:kanban_supply_areas,id',
            'replenishment_strategy'       => 'required|in:production,purchase,stock_transfer',
            'number_of_cards'              => 'required|integer|min:1|max:100',
            'replenishment_quantity'       => 'required|numeric|min:0.0001',
            'safety_stock_quantity'        => 'nullable|numeric|min:0',
            'replenishment_lead_time_days' => 'nullable|integer|min:1',
            'source_vendor_id'             => 'nullable|integer|exists:contacts,id',
            'source_warehouse_id'          => 'nullable|integer|exists:warehouses,id',
            'is_active'                    => 'nullable|boolean',
        ]);

        $cycle = $this->kanbanService->createControlCycle(
            array_merge($validated, ['organization_id' => $orgId])
        );

        return $this->success(
            $cycle->load(['product', 'supplyArea', 'cards']),
            'Control cycle created.',
            201
        );
    }

    /**
     * GET kanban/control-cycles/{controlCycle}
     */
    public function showControlCycle(KanbanControlCycle $controlCycle): JsonResponse
    {
        return $this->success($controlCycle->load(['product', 'supplyArea', 'cards']));
    }

    /**
     * PUT kanban/control-cycles/{controlCycle}
     */
    public function updateControlCycle(Request $request, KanbanControlCycle $controlCycle): JsonResponse
    {
        $validated = $request->validate([
            'replenishment_strategy'       => 'sometimes|in:production,purchase,stock_transfer',
            'replenishment_quantity'       => 'sometimes|numeric|min:0.0001',
            'safety_stock_quantity'        => 'nullable|numeric|min:0',
            'replenishment_lead_time_days' => 'nullable|integer|min:1',
            'source_vendor_id'             => 'nullable|integer|exists:contacts,id',
            'source_warehouse_id'          => 'nullable|integer|exists:warehouses,id',
            'is_active'                    => 'nullable|boolean',
        ]);

        $controlCycle->update($validated);

        return $this->success($controlCycle->fresh(['product', 'supplyArea']), 'Control cycle updated.');
    }

    /**
     * DELETE kanban/control-cycles/{controlCycle}
     */
    public function destroyControlCycle(KanbanControlCycle $controlCycle): JsonResponse
    {
        $controlCycle->delete();

        return $this->success(null, 'Control cycle deleted.');
    }

    // ── Cards ─────────────────────────────────────────────────────────────────

    /**
     * GET kanban/control-cycles/{controlCycle}/cards
     */
    public function cards(KanbanControlCycle $controlCycle): JsonResponse
    {
        $cards = $controlCycle->cards()
            ->orderBy('card_number')
            ->get();

        return $this->success($cards);
    }

    /**
     * POST kanban/cards/{kanbanCard}/empty
     */
    public function signalEmpty(KanbanCard $kanbanCard): JsonResponse
    {
        if (!$kanbanCard->canSignalEmpty()) {
            return $this->error(
                'INVALID_STATUS',
                "Card must be in 'full' status to signal empty. Current: {$kanbanCard->status}.",
                422
            );
        }

        $this->kanbanService->signalEmpty($kanbanCard);

        return $this->success($kanbanCard->fresh(), 'Card signalled as empty; replenishment triggered.');
    }

    /**
     * POST kanban/cards/{kanbanCard}/full
     * Body: { "quantity": 100.0 }
     */
    public function signalFull(Request $request, KanbanCard $kanbanCard): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        if (!$kanbanCard->canSignalFull()) {
            return $this->error(
                'INVALID_STATUS',
                "Card cannot be signalled full from status '{$kanbanCard->status}'.",
                422
            );
        }

        $this->kanbanService->signalFull($kanbanCard, (float) $validated['quantity']);

        return $this->success($kanbanCard->fresh(), 'Card signalled as full.');
    }

    // ── Board View ────────────────────────────────────────────────────────────

    /**
     * GET kanban/board
     */
    public function board(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('NO_ORGANIZATION', 'Organization context required.', 422);
        }

        $board = $this->kanbanService->getBoardView($orgId);

        return $this->success([
            'cycles' => $board,
            'count'  => count($board),
        ]);
    }
}
