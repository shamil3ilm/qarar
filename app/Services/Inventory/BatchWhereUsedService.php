<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\BatchWhereUsedRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BatchWhereUsedService
{
    public function record(array $data): BatchWhereUsedRecord
    {
        return BatchWhereUsedRecord::create(array_merge($data, [
            'recorded_by' => $data['recorded_by'] ?? Auth::id(),
            'used_at'     => $data['used_at'] ?? now(),
        ]));
    }

    public function getForBatch(int $batchId, array $filters = []): Collection
    {
        $query = BatchWhereUsedRecord::where('inventory_batch_id', $batchId)
            ->with(['product', 'warehouse', 'recorder']);

        if (!empty($filters['usage_type'])) {
            $query->where('usage_type', $filters['usage_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('used_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('used_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('used_at')->get();
    }

    /**
     * Returns a nested structure showing all downstream usages of the given batch.
     */
    public function getWhereUsedTree(int $batchId): array
    {
        $records = $this->getForBatch($batchId);

        return [
            'batch_id' => $batchId,
            'usages'   => $records->map(fn ($record) => [
                'id'               => $record->id,
                'usage_type'       => $record->usage_type,
                'reference_id'     => $record->reference_id,
                'reference_number' => $record->reference_number,
                'quantity_used'    => $record->quantity_used,
                'used_at'          => $record->used_at?->toIso8601String(),
                'product'          => $record->product ? [
                    'id'   => $record->product->id,
                    'name' => $record->product->name,
                ] : null,
                'warehouse' => $record->warehouse ? [
                    'id'   => $record->warehouse->id,
                    'name' => $record->warehouse->name,
                ] : null,
            ])->toArray(),
        ];
    }

    public function searchByReference(string $usageType, int $referenceId): Collection
    {
        return BatchWhereUsedRecord::where('usage_type', $usageType)
            ->where('reference_id', $referenceId)
            ->with(['inventoryBatch', 'product'])
            ->orderByDesc('used_at')
            ->get();
    }
}
