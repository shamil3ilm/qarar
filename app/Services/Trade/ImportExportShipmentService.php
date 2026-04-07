<?php

declare(strict_types=1);

namespace App\Services\Trade;

use App\Models\Trade\ImportExportShipment;
use App\Models\Trade\ImportExportShipmentItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportExportShipmentService
{
    /**
     * Create a new shipment.
     */
    public function create(array $data): ImportExportShipment
    {
        return DB::transaction(function () use ($data) {
            $shipment = ImportExportShipment::create($data);

            if (!empty($data['items'])) {
                $this->addItems($shipment, $data['items']);
            }

            return $shipment->fresh(['items', 'contact', 'purchaseOrder']);
        });
    }

    /**
     * Update an existing shipment.
     */
    public function update(ImportExportShipment $shipment, array $data): ImportExportShipment
    {
        if (!$shipment->isEditable()) {
            throw new InvalidArgumentException('This shipment cannot be updated in its current status.');
        }

        return DB::transaction(function () use ($shipment, $data) {
            $shipment->update($data);

            if (isset($data['items'])) {
                $shipment->items()->delete();
                $this->addItems($shipment, $data['items']);
            }

            $shipment->recalculateCifValue();

            return $shipment->fresh(['items', 'contact']);
        });
    }

    /**
     * Update the status of a shipment.
     */
    public function updateStatus(ImportExportShipment $shipment, string $status, array $extraData = []): ImportExportShipment
    {
        $validTransitions = [
            ImportExportShipment::STATUS_PENDING => [ImportExportShipment::STATUS_IN_TRANSIT, ImportExportShipment::STATUS_CANCELLED],
            ImportExportShipment::STATUS_IN_TRANSIT => [ImportExportShipment::STATUS_AT_PORT, ImportExportShipment::STATUS_CANCELLED],
            ImportExportShipment::STATUS_AT_PORT => [ImportExportShipment::STATUS_CUSTOMS_CLEARANCE, ImportExportShipment::STATUS_CANCELLED],
            ImportExportShipment::STATUS_CUSTOMS_CLEARANCE => [ImportExportShipment::STATUS_CLEARED, ImportExportShipment::STATUS_CANCELLED],
            ImportExportShipment::STATUS_CLEARED => [ImportExportShipment::STATUS_DELIVERED],
        ];

        $allowedStatuses = $validTransitions[$shipment->status] ?? [];

        if (!in_array($status, $allowedStatuses)) {
            throw new InvalidArgumentException("Cannot transition from '{$shipment->status}' to '{$status}'.");
        }

        return DB::transaction(function () use ($shipment, $status, $extraData) {
            $updateData = array_merge($extraData, ['status' => $status]);

            // Set date fields automatically based on status
            match ($status) {
                ImportExportShipment::STATUS_IN_TRANSIT => $updateData['actual_departure'] = $updateData['actual_departure'] ?? now()->toDateString(),
                ImportExportShipment::STATUS_DELIVERED => $updateData['delivery_date'] = $updateData['delivery_date'] ?? now()->toDateString(),
                default => null,
            };

            if ($status === ImportExportShipment::STATUS_AT_PORT || $status === ImportExportShipment::STATUS_CLEARED) {
                $updateData['actual_arrival'] = $updateData['actual_arrival'] ?? now()->toDateString();
            }

            $shipment->update($updateData);

            return $shipment->fresh();
        });
    }

    /**
     * Add items to a shipment.
     */
    public function addItems(ImportExportShipment $shipment, array $items): ImportExportShipment
    {
        return DB::transaction(function () use ($shipment, $items) {
            foreach ($items as $itemData) {
                $itemData['shipment_id'] = $shipment->id;
                ImportExportShipmentItem::create($itemData);
            }

            return $shipment->fresh(['items']);
        });
    }

    /**
     * Link a customs declaration to a shipment.
     */
    public function linkCustomsDeclaration(ImportExportShipment $shipment, int $declarationId): ImportExportShipment
    {
        return DB::transaction(function () use ($shipment, $declarationId) {
            $shipment->update(['customs_declaration_id' => $declarationId]);
            return $shipment->fresh(['customsDeclaration']);
        });
    }

    /**
     * Link a letter of credit to a shipment.
     */
    public function linkLC(ImportExportShipment $shipment, int $lcId): ImportExportShipment
    {
        return DB::transaction(function () use ($shipment, $lcId) {
            $shipment->update(['lc_id' => $lcId]);
            return $shipment->fresh(['letterOfCredit']);
        });
    }

    /**
     * Get shipment tracking info.
     */
    public function trackShipment(ImportExportShipment $shipment): array
    {
        return [
            'shipment_number' => $shipment->shipment_number,
            'shipment_type' => $shipment->shipment_type,
            'status' => $shipment->status,
            'transport_mode' => $shipment->transport_mode,
            'vessel_name' => $shipment->vessel_name,
            'voyage_number' => $shipment->voyage_number,
            'bill_of_lading' => $shipment->bill_of_lading,
            'airway_bill' => $shipment->airway_bill,
            'port_of_loading' => $shipment->port_of_loading,
            'port_of_discharge' => $shipment->port_of_discharge,
            'place_of_delivery' => $shipment->place_of_delivery,
            'estimated_departure' => $shipment->estimated_departure?->toDateString(),
            'actual_departure' => $shipment->actual_departure?->toDateString(),
            'estimated_arrival' => $shipment->estimated_arrival?->toDateString(),
            'actual_arrival' => $shipment->actual_arrival?->toDateString(),
            'delivery_date' => $shipment->delivery_date?->toDateString(),
            'customs_declaration_id' => $shipment->customs_declaration_id,
            'lc_id' => $shipment->lc_id,
        ];
    }

    /**
     * Get shipments that are in transit.
     */
    public function getInTransit(int $perPage = 20): LengthAwarePaginator
    {
        return ImportExportShipment::inTransit()
            ->with(['contact:id,name', 'items'])
            ->orderBy('estimated_arrival')
            ->paginate($perPage);
    }
}
