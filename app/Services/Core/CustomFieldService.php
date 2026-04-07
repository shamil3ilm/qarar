<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\CustomFieldDefinition;
use App\Models\Core\CustomFieldGroup;
use App\Models\Core\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomFieldService
{
    /**
     * Create a new custom field definition.
     */
    public function createDefinition(array $data): CustomFieldDefinition
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;

            // Auto-generate field_name from label if not provided
            if (empty($data['field_name'])) {
                $data['field_name'] = Str::snake($data['field_label']);
            }

            // Set display_order to next available if not provided
            if (!isset($data['display_order'])) {
                $data['display_order'] = (CustomFieldDefinition::where('organization_id', $data['organization_id'])
                    ->where('entity_type', $data['entity_type'])
                    ->max('display_order') ?? 0) + 1;
            }

            return CustomFieldDefinition::create($data);
        });
    }

    /**
     * Update a custom field definition.
     */
    public function updateDefinition(CustomFieldDefinition $definition, array $data): CustomFieldDefinition
    {
        return DB::transaction(function () use ($definition, $data) {
            // Prevent changing field_type if values exist
            if (isset($data['field_type']) && $data['field_type'] !== $definition->field_type) {
                $hasValues = $definition->values()->exists();
                if ($hasValues) {
                    throw new \RuntimeException(
                        'Cannot change field type when values already exist. Delete existing values first.'
                    );
                }
            }

            $definition->update($data);

            return $definition->fresh();
        });
    }

    /**
     * Delete a custom field definition and all its values.
     */
    public function deleteDefinition(CustomFieldDefinition $definition): bool
    {
        return DB::transaction(function () use ($definition) {
            $definition->values()->delete();
            return $definition->delete();
        });
    }

    /**
     * Create a custom field group.
     */
    public function createGroup(array $data): CustomFieldGroup
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;

            // Auto-generate slug from name if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name'], '_');
            }

            // Set display_order to next available if not provided
            if (!isset($data['display_order'])) {
                $data['display_order'] = (CustomFieldGroup::where('organization_id', $data['organization_id'])
                    ->where('entity_type', $data['entity_type'])
                    ->max('display_order') ?? 0) + 1;
            }

            return CustomFieldGroup::create($data);
        });
    }

    /**
     * Update a custom field group.
     */
    public function updateGroup(CustomFieldGroup $group, array $data): CustomFieldGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update($data);
            return $group->fresh();
        });
    }

    /**
     * Delete a custom field group (unlinks fields but does not delete them).
     */
    public function deleteGroup(CustomFieldGroup $group): bool
    {
        return DB::transaction(function () use ($group) {
            // Unlink fields from this group
            CustomFieldDefinition::where('organization_id', $group->organization_id)
                ->where('entity_type', $group->entity_type)
                ->where('field_group', $group->slug)
                ->update(['field_group' => null]);

            return $group->delete();
        });
    }

    /**
     * Get all custom field definitions for a given entity type.
     */
    public function getFieldsForEntity(string $entityType, ?int $organizationId = null): Collection
    {
        $organizationId = $organizationId ?? auth()->user()->organization_id;

        return CustomFieldDefinition::where('organization_id', $organizationId)
            ->forEntity($entityType)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Get fields grouped by their field_group for a given entity type.
     */
    public function getFieldsGrouped(string $entityType, ?int $organizationId = null): array
    {
        $organizationId = $organizationId ?? auth()->user()->organization_id;

        $fields = $this->getFieldsForEntity($entityType, $organizationId);

        $groups = CustomFieldGroup::where('organization_id', $organizationId)
            ->forEntity($entityType)
            ->active()
            ->ordered()
            ->get();

        $grouped = [];

        foreach ($groups as $group) {
            $grouped[] = [
                'group' => $group,
                'fields' => $fields->where('field_group', $group->slug)->values(),
            ];
        }

        // Fields without a group
        $ungrouped = $fields->whereNull('field_group')->values();
        if ($ungrouped->isNotEmpty()) {
            $grouped[] = [
                'group' => null,
                'fields' => $ungrouped,
            ];
        }

        return $grouped;
    }

    /**
     * Set custom field values for an entity.
     */
    public function setValues(Model $entity, array $fieldValues): Collection
    {
        return DB::transaction(function () use ($entity, $fieldValues) {
            $entityType = get_class($entity);
            $entityId = $entity->id;
            $results = collect();

            foreach ($fieldValues as $fieldName => $value) {
                $definition = CustomFieldDefinition::where('organization_id', $entity->organization_id)
                    ->where('entity_type', $this->resolveEntityType($entityType))
                    ->where('field_name', $fieldName)
                    ->active()
                    ->first();

                if (!$definition) {
                    continue;
                }

                // Validate the value
                $this->validateFieldValue($definition, $value);

                // Find or create the value record
                $fieldValue = CustomFieldValue::updateOrCreate(
                    [
                        'field_definition_id' => $definition->id,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                    ],
                    $this->buildValueColumns($definition, $value)
                );

                $results->push($fieldValue);
            }

            return $results;
        });
    }

    /**
     * Get all custom field values for an entity.
     */
    public function getValues(Model $entity): Collection
    {
        $entityType = get_class($entity);

        return CustomFieldValue::where('entity_type', $entityType)
            ->where('entity_id', $entity->id)
            ->with('definition')
            ->get()
            ->mapWithKeys(function (CustomFieldValue $value) {
                return [$value->definition->field_name => [
                    'definition' => $value->definition,
                    'value' => $value->getResolvedValue(),
                    'raw' => $value,
                ]];
            });
    }

    /**
     * Get custom field values as a simple key-value array.
     */
    public function getValuesSimple(Model $entity): array
    {
        $entityType = get_class($entity);

        return CustomFieldValue::where('entity_type', $entityType)
            ->where('entity_id', $entity->id)
            ->with('definition')
            ->get()
            ->mapWithKeys(function (CustomFieldValue $value) {
                return [$value->definition->field_name => $value->getResolvedValue()];
            })
            ->toArray();
    }

    /**
     * Validate a single field value against its definition.
     *
     * @throws \RuntimeException
     */
    public function validateFieldValue(CustomFieldDefinition $definition, mixed $value): bool
    {
        // Required check
        if ($definition->is_required && ($value === null || $value === '')) {
            throw new \RuntimeException(
                "Field '{$definition->field_label}' is required."
            );
        }

        // Allow null for non-required fields
        if ($value === null || $value === '') {
            return true;
        }

        // Type-specific validation
        match ($definition->field_type) {
            CustomFieldDefinition::TYPE_NUMBER => $this->validateNumber($value, $definition),
            CustomFieldDefinition::TYPE_DECIMAL => $this->validateDecimal($value, $definition),
            CustomFieldDefinition::TYPE_DATE => $this->validateDate($value),
            CustomFieldDefinition::TYPE_DATETIME => $this->validateDateTime($value),
            CustomFieldDefinition::TYPE_BOOLEAN => $this->validateBoolean($value),
            CustomFieldDefinition::TYPE_SELECT => $this->validateSelect($value, $definition),
            CustomFieldDefinition::TYPE_MULTISELECT => $this->validateMultiselect($value, $definition),
            CustomFieldDefinition::TYPE_EMAIL => $this->validateEmail($value),
            CustomFieldDefinition::TYPE_URL => $this->validateUrl($value),
            default => true,
        };

        // Custom validation rules
        if ($definition->validation) {
            $this->applyCustomValidation($value, $definition->validation, $definition->field_label);
        }

        return true;
    }

    /**
     * Build the value columns array for storage.
     */
    protected function buildValueColumns(CustomFieldDefinition $definition, mixed $value): array
    {
        $columns = [
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_boolean' => null,
            'value_json' => null,
        ];

        if ($value === null || $value === '') {
            return $columns;
        }

        $column = $definition->getValueColumn();
        $columns[$column] = $value;

        return $columns;
    }

    /**
     * Resolve the short entity type name from a full class name.
     */
    protected function resolveEntityType(string $className): string
    {
        $map = [
            'App\\Models\\Sales\\Invoice' => 'invoice',
            'App\\Models\\Sales\\Contact' => 'customer',
            'App\\Models\\Inventory\\Product' => 'product',
            'App\\Models\\HR\\Employee' => 'employee',
            'App\\Models\\CRM\\Lead' => 'lead',
            'App\\Models\\Purchase\\PurchaseOrder' => 'purchase_order',
            'App\\Models\\Purchase\\Bill' => 'bill',
        ];

        return $map[$className] ?? Str::snake(class_basename($className));
    }

    // Validation helpers

    protected function validateNumber(mixed $value, CustomFieldDefinition $definition): void
    {
        if (!is_numeric($value)) {
            throw new \RuntimeException("Field '{$definition->field_label}' must be a number.");
        }
    }

    protected function validateDecimal(mixed $value, CustomFieldDefinition $definition): void
    {
        if (!is_numeric($value)) {
            throw new \RuntimeException("Field '{$definition->field_label}' must be a decimal number.");
        }
    }

    protected function validateDate(mixed $value): void
    {
        if (strtotime((string) $value) === false) {
            throw new \RuntimeException('Invalid date format.');
        }
    }

    protected function validateDateTime(mixed $value): void
    {
        if (strtotime((string) $value) === false) {
            throw new \RuntimeException('Invalid datetime format.');
        }
    }

    protected function validateBoolean(mixed $value): void
    {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            throw new \RuntimeException('Value must be a boolean.');
        }
    }

    protected function validateSelect(mixed $value, CustomFieldDefinition $definition): void
    {
        if (!$definition->options) {
            return;
        }

        $validValues = array_column($definition->options, 'value');
        if (!in_array($value, $validValues)) {
            throw new \RuntimeException("Invalid option for field '{$definition->field_label}'.");
        }
    }

    protected function validateMultiselect(mixed $value, CustomFieldDefinition $definition): void
    {
        if (!is_array($value)) {
            throw new \RuntimeException("Field '{$definition->field_label}' must be an array.");
        }

        if (!$definition->options) {
            return;
        }

        $validValues = array_column($definition->options, 'value');
        foreach ($value as $item) {
            if (!in_array($item, $validValues)) {
                throw new \RuntimeException("Invalid option '{$item}' for field '{$definition->field_label}'.");
            }
        }
    }

    protected function validateEmail(mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email address.');
        }
    }

    protected function validateUrl(mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Invalid URL.');
        }
    }

    protected function applyCustomValidation(mixed $value, array $rules, string $label): void
    {
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            throw new \RuntimeException("Field '{$label}' must be at least {$rules['min']}.");
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            throw new \RuntimeException("Field '{$label}' must be at most {$rules['max']}.");
        }

        if (isset($rules['min_length']) && is_string($value) && strlen($value) < $rules['min_length']) {
            throw new \RuntimeException("Field '{$label}' must be at least {$rules['min_length']} characters.");
        }

        if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
            throw new \RuntimeException("Field '{$label}' must be at most {$rules['max_length']} characters.");
        }

        if (isset($rules['pattern']) && is_string($value)) {
            // Suppress errors so an invalid regex does not raise a PHP warning;
            // preg_match returns false when the pattern itself is invalid.
            set_error_handler(null);
            $matched = @preg_match($rules['pattern'], $value);
            restore_error_handler();

            if ($matched === false) {
                // Invalid regex stored in the definition — reject loudly so admins can fix it.
                throw new \RuntimeException("Field '{$label}' has an invalid validation pattern configured.");
            }
            if (!$matched) {
                throw new \RuntimeException("Field '{$label}' does not match the required pattern.");
            }
        }
    }
}
