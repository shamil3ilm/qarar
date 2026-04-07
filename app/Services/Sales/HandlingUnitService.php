<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\HandlingUnit;
use App\Models\Sales\HandlingUnitItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HandlingUnitService
{
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = HandlingUnit::query();

        if (!empty($filters['shipment_id'])) {
            $query->where('shipment_id', $filters['shipment_id']);
        }
        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }
        if (!empty($filters['hu_type'])) {
            $query->where('hu_type', $filters['hu_type']);
        }
        if (isset($filters['is_sealed'])) {
            $query->where('is_sealed', $filters['is_sealed']);
        }

        return $query->with(['shipment', 'salesOrder', 'items.product'])->latest()->paginate($perPage);
    }

    public function create(array $data): HandlingUnit
    {
        if (empty($data['hu_number'])) {
            $data['hu_number'] = $this->generateHuNumber();
        }

        return DB::transaction(function () use ($data): HandlingUnit {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $hu = HandlingUnit::create($data);

            foreach ($items as $item) {
                $hu->items()->create(array_merge($item, [
                    'organization_id' => $hu->organization_id,
                ]));
            }

            return $hu->load(['shipment', 'salesOrder', 'items.product']);
        });
    }

    public function update(HandlingUnit $hu, array $data): HandlingUnit
    {
        $hu->update($data);
        return $hu->fresh(['shipment', 'salesOrder', 'items.product']);
    }

    public function addItem(HandlingUnit $hu, array $data): HandlingUnitItem
    {
        if ($hu->is_sealed) {
            throw new \RuntimeException('Cannot add items to a sealed handling unit.');
        }

        return HandlingUnitItem::create(array_merge($data, [
            'handling_unit_id' => $hu->id,
            'organization_id' => $hu->organization_id,
        ]));
    }

    public function removeItem(HandlingUnit $hu, int $itemId): void
    {
        if ($hu->is_sealed) {
            throw new \RuntimeException('Cannot remove items from a sealed handling unit.');
        }

        HandlingUnitItem::where('handling_unit_id', $hu->id)
            ->where('id', $itemId)
            ->delete();
    }

    public function seal(HandlingUnit $hu): HandlingUnit
    {
        return $hu->seal();
    }

    public function generateHuNumber(): string
    {
        return 'HU-' . strtoupper(Str::random(4)) . '-' . now()->format('YmdHis');
    }

    /**
     * Create handling units from packing instructions for a sales order.
     *
     * @param  array  $packingInstructions  Array of packing groups, each with 'hu_type' and 'items'
     */
    public function packOrderItems(int $salesOrderId, array $packingInstructions): array
    {
        $created = [];

        DB::transaction(function () use ($salesOrderId, $packingInstructions, &$created): void {
            foreach ($packingInstructions as $instruction) {
                $data = [
                    'sales_order_id' => $salesOrderId,
                    'hu_type' => $instruction['hu_type'] ?? 'box',
                    'items' => $instruction['items'] ?? [],
                    'gross_weight' => $instruction['gross_weight'] ?? null,
                    'net_weight' => $instruction['net_weight'] ?? null,
                    'notes' => $instruction['notes'] ?? null,
                ];

                $created[] = $this->create($data);
            }
        });

        return $created;
    }

    public function getPackingList(int $shipmentId): array
    {
        $handlingUnits = HandlingUnit::forShipment($shipmentId)
            ->with(['items.product', 'items.inventoryBatch'])
            ->get();

        return [
            'shipment_id' => $shipmentId,
            'total_handling_units' => $handlingUnits->count(),
            'total_gross_weight' => $handlingUnits->sum('gross_weight'),
            'total_net_weight' => $handlingUnits->sum('net_weight'),
            'handling_units' => $handlingUnits->map(fn (HandlingUnit $hu): array => [
                'id' => $hu->id,
                'uuid' => $hu->uuid,
                'hu_number' => $hu->hu_number,
                'hu_type' => $hu->hu_type,
                'sscc_number' => $hu->sscc_number,
                'gross_weight' => $hu->gross_weight,
                'net_weight' => $hu->net_weight,
                'volume' => $hu->volume,
                'dimensions' => [
                    'length' => $hu->length,
                    'width' => $hu->width,
                    'height' => $hu->height,
                ],
                'is_sealed' => $hu->is_sealed,
                'items' => $hu->items->map(fn (HandlingUnitItem $item): array => [
                    'id' => $item->id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'quantity' => $item->quantity,
                    'weight' => $item->weight,
                    'batch_number' => $item->inventoryBatch?->batch_number ?? null,
                ])->toArray(),
            ])->toArray(),
        ];
    }
}
