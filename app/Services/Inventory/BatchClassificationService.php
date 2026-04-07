<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\BatchCharacteristic;
use App\Models\Inventory\BatchCharacteristicValue;
use App\Models\Inventory\BatchClass;
use App\Models\Inventory\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class BatchClassificationService
{
    public function createClass(int $orgId, array $data): BatchClass
    {
        return BatchClass::create(array_merge($data, [
            'organization_id' => $orgId,
        ]));
    }

    public function updateClass(BatchClass $batchClass, array $data): BatchClass
    {
        $batchClass->update($data);

        return $batchClass->fresh();
    }

    public function addCharacteristic(BatchClass $batchClass, array $data): BatchCharacteristic
    {
        return BatchCharacteristic::create(array_merge($data, [
            'organization_id' => $batchClass->organization_id,
            'batch_class_id'  => $batchClass->id,
        ]));
    }

    public function assignClassToBatch(InventoryBatch $batch, int $classId): void
    {
        $class = BatchClass::where('organization_id', $batch->organization_id)
            ->findOrFail($classId);

        $batch->update(['batch_class_id' => $class->id]);
    }

    public function setCharacteristicValue(
        InventoryBatch $batch,
        int $characteristicId,
        mixed $value
    ): BatchCharacteristicValue {
        $characteristic = BatchCharacteristic::findOrFail($characteristicId);

        if (!$characteristic->validate($value)) {
            throw new InvalidArgumentException(
                "Value '{$value}' is invalid for characteristic '{$characteristic->characteristic_name}'."
            );
        }

        $payload = $this->buildValuePayload($characteristic, $value);

        return BatchCharacteristicValue::updateOrCreate(
            [
                'inventory_batch_id'      => $batch->id,
                'batch_characteristic_id' => $characteristicId,
            ],
            array_merge($payload, [
                'organization_id' => $batch->organization_id,
            ])
        );
    }

    public function getValuesForBatch(InventoryBatch $batch): Collection
    {
        return BatchCharacteristicValue::where('inventory_batch_id', $batch->id)
            ->with('characteristic')
            ->get();
    }

    public function findBatchesByCharacteristic(int $characteristicId, mixed $value): Collection
    {
        $characteristic = BatchCharacteristic::findOrFail($characteristicId);
        $column         = $this->getValueColumn($characteristic->data_type);

        return BatchCharacteristicValue::where('batch_characteristic_id', $characteristicId)
            ->where($column, $value)
            ->with('inventoryBatch')
            ->get()
            ->pluck('inventoryBatch');
    }

    private function buildValuePayload(BatchCharacteristic $characteristic, mixed $value): array
    {
        return match ($characteristic->data_type) {
            BatchCharacteristic::TYPE_NUMERIC => ['numeric_value' => $value, 'text_value' => null, 'date_value' => null, 'boolean_value' => null],
            BatchCharacteristic::TYPE_DATE    => ['date_value' => $value, 'text_value' => null, 'numeric_value' => null, 'boolean_value' => null],
            BatchCharacteristic::TYPE_BOOLEAN => ['boolean_value' => (bool) $value, 'text_value' => null, 'numeric_value' => null, 'date_value' => null],
            default                           => ['text_value' => (string) $value, 'numeric_value' => null, 'date_value' => null, 'boolean_value' => null],
        };
    }

    private function getValueColumn(string $dataType): string
    {
        return match ($dataType) {
            BatchCharacteristic::TYPE_NUMERIC => 'numeric_value',
            BatchCharacteristic::TYPE_DATE    => 'date_value',
            BatchCharacteristic::TYPE_BOOLEAN => 'boolean_value',
            default                           => 'text_value',
        };
    }
}
