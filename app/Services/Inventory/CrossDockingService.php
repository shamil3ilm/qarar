<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\CrossDockingOrder;
use App\Models\Inventory\CrossDockingOrderLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrossDockingService
{
    /**
     * Create a new cross-docking order with lines.
     *
     * Expected keys in $data:
     *   organization_id, warehouse_id, inbound_source_type, inbound_source_id,
     *   outbound_dest_type, outbound_dest_id, planned_date, dock_door_id?,
     *   created_by?, notes?,
     *   lines[] => [product_id, quantity, unit_id?]
     */
    public function createCrossDockingOrder(array $data): CrossDockingOrder
    {
        return DB::transaction(function () use ($data): CrossDockingOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $data['status'] = CrossDockingOrder::STATUS_PLANNED;

            $order = CrossDockingOrder::create($data);

            foreach ($lines as $line) {
                $order->lines()->create([
                    'product_id'          => $line['product_id'],
                    'quantity'            => $line['quantity'],
                    'unit_id'             => $line['unit_id'] ?? null,
                    'quantity_transferred' => 0,
                    'status'              => CrossDockingOrderLine::STATUS_PENDING,
                ]);
            }

            return $order->load('lines');
        });
    }

    /**
     * Transition a planned order to in_progress.
     */
    public function startTransfer(CrossDockingOrder $order): void
    {
        if (!$order->isPlanned()) {
            throw new RuntimeException(
                "Only planned orders can be started. Current status: {$order->status}."
            );
        }

        $order->update(['status' => CrossDockingOrder::STATUS_IN_PROGRESS]);
    }

    /**
     * Record a quantity transfer against a specific order line.
     */
    public function transferLine(CrossDockingOrderLine $line, float $quantity): void
    {
        if ($line->isTransferred()) {
            throw new RuntimeException('This line has already been fully transferred.');
        }

        $remaining = $line->getRemainingQuantity();

        if ($quantity > $remaining) {
            throw new RuntimeException(
                "Transfer quantity ({$quantity}) exceeds remaining ({$remaining})."
            );
        }

        $newTransferred = (float) bcadd((string) $line->quantity_transferred, (string) $quantity, 4);
        $isFullyTransferred = bccomp((string) $newTransferred, (string) $line->quantity, 4) >= 0;

        $line->update([
            'quantity_transferred' => $newTransferred,
            'status' => $isFullyTransferred
                ? CrossDockingOrderLine::STATUS_TRANSFERRED
                : CrossDockingOrderLine::STATUS_PARTIAL,
        ]);
    }

    /**
     * Complete a cross-docking order (all lines must be transferred or partial allowed).
     */
    public function complete(CrossDockingOrder $order): void
    {
        if (!$order->isInProgress()) {
            throw new RuntimeException(
                "Only in-progress orders can be completed. Current status: {$order->status}."
            );
        }

        $order->update([
            'status'      => CrossDockingOrder::STATUS_COMPLETED,
            'actual_date' => now(),
        ]);
    }

    /**
     * Identify cross-docking opportunities for a warehouse.
     *
     * Finds cross_docking_orders in 'planned' status for the given warehouse
     * with unfinished lines, ordered by planned_date ascending.
     */
    public function identifyOpportunities(int $warehouseId): Collection
    {
        return CrossDockingOrder::where('warehouse_id', $warehouseId)
            ->where('status', CrossDockingOrder::STATUS_PLANNED)
            ->whereHas('lines', fn ($q) => $q->whereIn('status', [
                CrossDockingOrderLine::STATUS_PENDING,
                CrossDockingOrderLine::STATUS_PARTIAL,
            ]))
            ->with(['lines.product'])
            ->orderBy('planned_date')
            ->get();
    }

    /**
     * Get all active cross-docking orders for a warehouse.
     */
    public function getActiveOrders(int $warehouseId): Collection
    {
        return CrossDockingOrder::where('warehouse_id', $warehouseId)
            ->whereIn('status', [CrossDockingOrder::STATUS_PLANNED, CrossDockingOrder::STATUS_IN_PROGRESS])
            ->with(['lines.product'])
            ->orderBy('planned_date')
            ->get();
    }
}
