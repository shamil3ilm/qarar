<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\SerialNumberMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SerialNumberService
{
    /**
     * Create a single serial number, validating uniqueness per product within the org.
     */
    public function create(array $data): SerialNumber
    {
        $orgId     = $data['organization_id'] ?? auth()->user()->organization_id;
        $productId = $data['product_id'];
        $serial    = $data['serial_number'];

        $exists = SerialNumber::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('product_id', $productId)
            ->where('serial_number', $serial)
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException(
                "Serial number '{$serial}' already exists for this product."
            );
        }

        return SerialNumber::create($data);
    }

    /**
     * Bulk-create multiple serial numbers for a single product.
     *
     * @param  string[]  $serials
     * @return SerialNumber[]
     */
    public function bulkCreate(int $productId, array $serials): array
    {
        return DB::transaction(function () use ($productId, $serials): array {
            $created = [];
            foreach ($serials as $serial) {
                $created[] = $this->create([
                    'product_id'    => $productId,
                    'serial_number' => $serial,
                ]);
            }
            return $created;
        });
    }

    /**
     * Mark a serial as received into a warehouse, record movement.
     */
    public function receive(
        SerialNumber $sn,
        int $warehouseId,
        ?int $locationId,
        string $docType,
        int $docId
    ): void {
        DB::transaction(function () use ($sn, $warehouseId, $locationId, $docType, $docId): void {
            $sn->update([
                'status'               => SerialNumber::STATUS_IN_STOCK,
                'warehouse_id'         => $warehouseId,
                'location_id'          => $locationId,
                'received_at'          => now(),
                'current_document_type' => $docType,
                'current_document_id'  => $docId,
            ]);

            $this->recordMovement($sn, SerialNumberMovement::TYPE_RECEIPT, [
                'to_warehouse_id' => $warehouseId,
                'document_type'   => $docType,
                'document_id'     => $docId,
            ]);
        });
    }

    /**
     * Issue / sell a serial number.
     */
    public function issue(
        SerialNumber $sn,
        int $warehouseId,
        ?int $contactId,
        string $docType,
        int $docId
    ): void {
        if (! $sn->isInStock()) {
            throw new \InvalidArgumentException(
                "Serial number '{$sn->serial_number}' is not in stock (current status: {$sn->status})."
            );
        }

        DB::transaction(function () use ($sn, $warehouseId, $contactId, $docType, $docId): void {
            $sn->update([
                'status'               => SerialNumber::STATUS_SOLD,
                'sold_at'              => now(),
                'sold_to_contact_id'   => $contactId,
                'current_document_type' => $docType,
                'current_document_id'  => $docId,
            ]);

            $this->recordMovement($sn, SerialNumberMovement::TYPE_ISSUE, [
                'from_warehouse_id' => $warehouseId,
                'document_type'     => $docType,
                'document_id'       => $docId,
            ]);
        });
    }

    /**
     * Transfer a serial number to another warehouse.
     */
    public function transfer(SerialNumber $sn, int $toWarehouseId, ?int $toLocationId): void
    {
        DB::transaction(function () use ($sn, $toWarehouseId, $toLocationId): void {
            $fromWarehouseId = $sn->warehouse_id;

            $sn->update([
                'status'       => SerialNumber::STATUS_IN_STOCK,
                'warehouse_id' => $toWarehouseId,
                'location_id'  => $toLocationId,
            ]);

            $this->recordMovement($sn, SerialNumberMovement::TYPE_TRANSFER, [
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id'   => $toWarehouseId,
            ]);
        });
    }

    /**
     * Scrap a serial number.
     */
    public function scrap(SerialNumber $sn, string $reason): void
    {
        DB::transaction(function () use ($sn, $reason): void {
            $fromWarehouseId = $sn->warehouse_id;

            $sn->update([
                'status' => SerialNumber::STATUS_SCRAPPED,
                'notes'  => trim(($sn->notes ?? '') . "\nScrapped: {$reason}"),
            ]);

            $this->recordMovement($sn, SerialNumberMovement::TYPE_SCRAP, [
                'from_warehouse_id' => $fromWarehouseId,
                'notes'             => $reason,
            ]);
        });
    }

    /**
     * Search / paginate serial numbers.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = SerialNumber::with(['product', 'warehouse'])
            ->when(isset($filters['product_id']), fn ($q) => $q->forProduct((int) $filters['product_id']))
            ->when(isset($filters['warehouse_id']), fn ($q) => $q->inWarehouse((int) $filters['warehouse_id']))
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['serial_number']),
                fn ($q) => $q->where('serial_number', 'like', '%' . $filters['serial_number'] . '%')
            )
            ->latest();

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Return the full movement history for a serial number.
     */
    public function history(SerialNumber $sn): Collection
    {
        return $sn->movements()->with(['fromWarehouse', 'toWarehouse', 'movedBy'])->get();
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function recordMovement(SerialNumber $sn, string $type, array $extra = []): SerialNumberMovement
    {
        return SerialNumberMovement::create(array_merge([
            'serial_number_id' => $sn->id,
            'movement_type'    => $type,
            'moved_by'         => auth()->id(),
            'moved_at'         => now(),
        ], $extra));
    }
}
