<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\ClassAssignment;
use App\Models\Core\ClassCharacteristic;
use App\Models\Core\ClassCharacteristicValue;
use App\Models\Core\ClassificationClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClassificationService
{
    /**
     * List classification classes for an object type.
     */
    public function listClasses(string $objectType, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ClassificationClass::with(['characteristics'])
            ->where('object_type', $objectType)
            ->orderBy('class_name');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('class_code', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a classification class.
     */
    public function createClass(array $data): ClassificationClass
    {
        return DB::transaction(static fn () => ClassificationClass::create($data));
    }

    /**
     * Update a classification class.
     */
    public function updateClass(ClassificationClass $class, array $data): ClassificationClass
    {
        DB::transaction(static fn () => $class->update($data));

        return $class->fresh();
    }

    /**
     * Add a characteristic to a class.
     */
    public function addCharacteristic(ClassificationClass $class, array $data): ClassCharacteristic
    {
        return DB::transaction(static function () use ($class, $data): ClassCharacteristic {
            $data['classification_class_id'] = $class->id;
            $data['organization_id'] = $class->organization_id;

            return ClassCharacteristic::create($data);
        });
    }

    /**
     * Update a characteristic.
     */
    public function updateCharacteristic(ClassCharacteristic $characteristic, array $data): ClassCharacteristic
    {
        DB::transaction(static fn () => $characteristic->update($data));

        return $characteristic->fresh();
    }

    /**
     * Assign a class to an object.
     */
    public function assignClassToObject(
        string $objectType,
        int $objectId,
        int $classId,
        int $orgId,
    ): ClassAssignment {
        return DB::transaction(static function () use ($objectType, $objectId, $classId, $orgId): ClassAssignment {
            return ClassAssignment::firstOrCreate(
                [
                    'classification_class_id' => $classId,
                    'object_type'             => $objectType,
                    'object_id'               => $objectId,
                ],
                [
                    'organization_id' => $orgId,
                    'assigned_at'     => now(),
                ]
            );
        });
    }

    /**
     * Set a characteristic value for an object.
     */
    public function setCharacteristicValue(
        string $objectType,
        int $objectId,
        int $characteristicId,
        mixed $value,
        int $orgId,
    ): ClassCharacteristicValue {
        $characteristic = ClassCharacteristic::findOrFail($characteristicId);

        $valueData = [
            'organization_id'         => $orgId,
            'class_characteristic_id' => $characteristicId,
            'object_type'             => $objectType,
            'object_id'               => $objectId,
        ];

        // Set the typed value field
        match ($characteristic->data_type) {
            'text', 'list' => $valueData['text_value'] = (string) $value,
            'numeric'      => $valueData['numeric_value'] = (float) $value,
            'date'         => $valueData['date_value'] = (string) $value,
            'boolean'      => $valueData['boolean_value'] = (bool) $value,
            default        => $valueData['text_value'] = (string) $value,
        };

        return DB::transaction(static function () use ($valueData, $characteristicId, $objectType, $objectId): ClassCharacteristicValue {
            return ClassCharacteristicValue::updateOrCreate(
                [
                    'class_characteristic_id' => $characteristicId,
                    'object_type'             => $objectType,
                    'object_id'               => $objectId,
                ],
                $valueData
            );
        });
    }

    /**
     * Get all classes assigned to an object, with their characteristic values.
     */
    public function getClassesForObject(string $objectType, int $objectId): Collection
    {
        $assignments = ClassAssignment::with([
            'classificationClass.characteristics',
        ])
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->get();

        $classIds = $assignments->pluck('classification_class_id');

        // Load characteristic values for this object
        $charIds = ClassCharacteristic::whereIn('classification_class_id', $classIds)->pluck('id');

        $values = ClassCharacteristicValue::whereIn('class_characteristic_id', $charIds)
            ->where('object_type', $objectType)
            ->where('object_id', $objectId)
            ->get()
            ->keyBy('class_characteristic_id');

        // Merge values into characteristics
        foreach ($assignments as $assignment) {
            foreach ($assignment->classificationClass->characteristics as $char) {
                $char->setRelation('value', $values->get($char->id));
            }
        }

        return $assignments;
    }

    /**
     * Search objects by characteristic criteria.
     *
     * Criteria format: [['characteristic_id' => 1, 'value' => 'something'], ...]
     */
    public function searchByCharacteristics(string $objectType, array $criteria): Collection
    {
        $query = ClassCharacteristicValue::where('object_type', $objectType);

        foreach ($criteria as $criterion) {
            $charId = $criterion['characteristic_id'];
            $value = $criterion['value'];

            $characteristic = ClassCharacteristic::find($charId);

            if ($characteristic === null) {
                continue;
            }

            $query->orWhere(function ($q) use ($charId, $value, $characteristic): void {
                $q->where('class_characteristic_id', $charId);

                match ($characteristic->data_type) {
                    'text', 'list' => $q->where('text_value', 'like', "%{$value}%"),
                    'numeric'      => $q->where('numeric_value', $value),
                    'date'         => $q->where('date_value', $value),
                    'boolean'      => $q->where('boolean_value', (bool) $value),
                    default        => $q->where('text_value', $value),
                };
            });
        }

        return $query->select('object_type', 'object_id')->distinct()->get();
    }
}
