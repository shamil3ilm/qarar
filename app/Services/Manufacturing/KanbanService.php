<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\KanbanCard;
use App\Models\Manufacturing\KanbanControlCycle;
use App\Models\Manufacturing\KanbanSupplyArea;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Inventory\StockTransfer;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KanbanService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * Create a Kanban control cycle and auto-create the configured number of cards.
     */
    public function createControlCycle(array $data): KanbanControlCycle
    {
        return DB::transaction(function () use ($data): KanbanControlCycle {
            $cycle = KanbanControlCycle::create([
                'organization_id'              => $data['organization_id'],
                'product_id'                   => $data['product_id'],
                'supply_area_id'               => $data['supply_area_id'],
                'replenishment_strategy'       => $data['replenishment_strategy'] ?? KanbanControlCycle::STRATEGY_PRODUCTION,
                'number_of_cards'              => $data['number_of_cards'] ?? 1,
                'replenishment_quantity'       => $data['replenishment_quantity'],
                'safety_stock_quantity'        => $data['safety_stock_quantity'] ?? 0,
                'replenishment_lead_time_days' => $data['replenishment_lead_time_days'] ?? 1,
                'source_vendor_id'             => $data['source_vendor_id'] ?? null,
                'source_warehouse_id'          => $data['source_warehouse_id'] ?? null,
                'is_active'                    => $data['is_active'] ?? true,
            ]);

            // Auto-create N cards
            $numberOfCards = (int) $cycle->number_of_cards;
            for ($i = 1; $i <= $numberOfCards; $i++) {
                KanbanCard::create([
                    'control_cycle_id' => $cycle->id,
                    'card_number'      => $this->generateCardNumber($cycle, $i),
                    'status'           => KanbanCard::STATUS_FULL,
                    'current_quantity' => (float) $cycle->replenishment_quantity,
                ]);
            }

            return $cycle->fresh(['cards']);
        });
    }

    /**
     * Signal that a Kanban card is now empty.
     * Sets status=empty, emptied_at=now, then checks whether to trigger replenishment.
     */
    public function signalEmpty(KanbanCard $card): void
    {
        DB::transaction(function () use ($card): void {
            $card->update([
                'status'     => KanbanCard::STATUS_EMPTY,
                'emptied_at' => now(),
            ]);

            // Trigger replenishment for every empty card (threshold = 1)
            $this->triggerReplenishment($card);
        });
    }

    /**
     * Trigger a replenishment document based on the control cycle strategy.
     */
    public function triggerReplenishment(KanbanCard $card): void
    {
        DB::transaction(function () use ($card): void {
            $cycle = $card->controlCycle;

            if ($cycle === null) {
                return;
            }

            [$docId, $docType] = match ($cycle->replenishment_strategy) {
                KanbanControlCycle::STRATEGY_PRODUCTION     => $this->createProductionWorkOrder($cycle),
                KanbanControlCycle::STRATEGY_PURCHASE       => $this->createPurchaseOrderLine($cycle),
                KanbanControlCycle::STRATEGY_STOCK_TRANSFER => $this->createStockTransferOrder($cycle),
                default => [null, null],
            };

            $card->update([
                'status'                    => KanbanCard::STATUS_IN_REPLENISHMENT,
                'replenishment_triggered_at' => now(),
                'triggered_document_id'     => $docId,
                'triggered_document_type'   => $docType,
            ]);
        });
    }

    /**
     * Signal that a Kanban card has been filled (replenishment complete).
     */
    public function signalFull(KanbanCard $card, float $quantity): void
    {
        DB::transaction(function () use ($card, $quantity): void {
            $card->update([
                'status'           => KanbanCard::STATUS_FULL,
                'filled_at'        => now(),
                'current_quantity' => $quantity,
            ]);
        });
    }

    /**
     * Return all active control cycles with card status summaries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBoardView(int $orgId): array
    {
        $cycles = KanbanControlCycle::withoutGlobalScope('organization')
            ->with(['product', 'supplyArea', 'cards'])
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->get();

        return $cycles->map(function (KanbanControlCycle $cycle): array {
            $cards        = $cycle->cards;
            $statusCounts = $cards->groupBy('status')->map->count();

            return [
                'control_cycle_id'             => $cycle->id,
                'uuid'                         => $cycle->uuid,
                'product_id'                   => $cycle->product_id,
                'product_name'                 => $cycle->product?->name ?? 'Unknown',
                'supply_area'                  => $cycle->supplyArea?->name,
                'replenishment_strategy'       => $cycle->replenishment_strategy,
                'number_of_cards'              => (int) $cycle->number_of_cards,
                'replenishment_quantity'       => (float) $cycle->replenishment_quantity,
                'replenishment_lead_time_days' => (int) $cycle->replenishment_lead_time_days,
                'card_summary'                 => [
                    KanbanCard::STATUS_FULL             => (int) ($statusCounts[KanbanCard::STATUS_FULL] ?? 0),
                    KanbanCard::STATUS_EMPTY            => (int) ($statusCounts[KanbanCard::STATUS_EMPTY] ?? 0),
                    KanbanCard::STATUS_IN_REPLENISHMENT => (int) ($statusCounts[KanbanCard::STATUS_IN_REPLENISHMENT] ?? 0),
                    KanbanCard::STATUS_WAITING          => (int) ($statusCounts[KanbanCard::STATUS_WAITING] ?? 0),
                ],
                'cards' => $cards->map(fn(KanbanCard $c): array => [
                    'id'              => $c->id,
                    'card_number'     => $c->card_number,
                    'status'          => $c->status,
                    'current_quantity' => (float) $c->current_quantity,
                    'emptied_at'      => $c->emptied_at?->toDateTimeString(),
                    'filled_at'       => $c->filled_at?->toDateTimeString(),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a sequential card number for a control cycle.
     */
    private function generateCardNumber(KanbanControlCycle $cycle, int $index): string
    {
        return sprintf('KC-%d-%03d', $cycle->id, $index);
    }

    /**
     * Create a WorkOrder for Kanban production replenishment.
     *
     * @return array{0: int, 1: string}
     */
    private function createProductionWorkOrder(KanbanControlCycle $cycle): array
    {
        $workOrderNumber = $this->numberGenerator->generate('WO');

        $workOrder = WorkOrder::create([
            'organization_id'   => $cycle->organization_id,
            'work_order_number' => $workOrderNumber,
            'product_id'        => $cycle->product_id,
            'planned_quantity'  => (float) $cycle->replenishment_quantity,
            'planned_start_date' => now()->toDateString(),
            'planned_end_date'  => now()->addDays((int) $cycle->replenishment_lead_time_days)->toDateString(),
            'status'            => WorkOrder::STATUS_PENDING,
            'priority'          => WorkOrder::PRIORITY_NORMAL,
            'notes'             => 'Auto-generated by Kanban replenishment.',
        ]);

        return [$workOrder->id, 'work_order'];
    }

    /**
     * Create a draft PurchaseOrder for Kanban purchase replenishment.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function createPurchaseOrderLine(KanbanControlCycle $cycle): array
    {
        if ($cycle->source_vendor_id === null) {
            Log::warning("Kanban cycle {$cycle->id} has no source vendor; skipping purchase order creation.");

            return [null, null];
        }

        $orderNumber = $this->numberGenerator->generate('PO');

        $po = PurchaseOrder::create([
            'organization_id'  => $cycle->organization_id,
            'order_number'     => $orderNumber,
            'supplier_id'      => $cycle->source_vendor_id,
            'supplier_name'    => $cycle->sourceVendor?->getDisplayName() ?? 'Unknown',
            'order_date'       => now()->toDateString(),
            'expected_date'    => now()->addDays((int) $cycle->replenishment_lead_time_days)->toDateString(),
            'status'           => PurchaseOrder::STATUS_DRAFT,
            'notes'            => 'Auto-generated by Kanban replenishment.',
        ]);

        $po->lines()->create([
            'product_id'  => $cycle->product_id,
            'quantity'    => (float) $cycle->replenishment_quantity,
            'unit_price'  => 0,
            'description' => 'Kanban replenishment',
        ]);

        return [$po->id, 'purchase_order'];
    }

    /**
     * Create a StockTransfer for Kanban stock-transfer replenishment.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function createStockTransferOrder(KanbanControlCycle $cycle): array
    {
        if ($cycle->source_warehouse_id === null) {
            Log::warning("Kanban cycle {$cycle->id} has no source warehouse; skipping transfer creation.");

            return [null, null];
        }

        $supplyArea      = $cycle->supplyArea;
        $toWarehouseId   = $supplyArea?->warehouse_id;

        if ($toWarehouseId === null || $toWarehouseId === $cycle->source_warehouse_id) {
            Log::warning("Kanban cycle {$cycle->id} supply area warehouse equals source; skipping transfer.");

            return [null, null];
        }

        $transferNumber = $this->numberGenerator->generate('TRF');

        $transfer = StockTransfer::create([
            'organization_id'    => $cycle->organization_id,
            'transfer_number'    => $transferNumber,
            'from_warehouse_id'  => $cycle->source_warehouse_id,
            'to_warehouse_id'    => $toWarehouseId,
            'transfer_date'      => now()->toDateString(),
            'status'             => 'draft',
            'notes'              => 'Auto-generated by Kanban replenishment.',
        ]);

        $transfer->lines()->create([
            'product_id'       => $cycle->product_id,
            'requested_quantity' => (float) $cycle->replenishment_quantity,
            'unit_cost'        => 0,
        ]);

        return [$transfer->id, 'stock_transfer'];
    }
}
