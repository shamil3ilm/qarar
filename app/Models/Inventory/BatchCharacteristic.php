<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchCharacteristic extends Model
{
    use BelongsToOrganization, HasUuid;

    public const TYPE_TEXT    = 'text';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_DATE    = 'date';
    public const TYPE_BOOLEAN = 'boolean';

    protected $fillable = [
        'organization_id',
        'batch_class_id',
        'characteristic_code',
        'characteristic_name',
        'data_type',
        'unit_of_measure',
        'is_required',
        'min_value',
        'max_value',
        'allowed_values',
    ];

    protected function casts(): array
    {
        return [
            'is_required'    => 'boolean',
            'min_value'      => 'decimal:4',
            'max_value'      => 'decimal:4',
            'allowed_values' => 'array',
        ];
    }

    // Relationships

    public function batchClass(): BelongsTo
    {
        return $this->belongsTo(BatchClass::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(BatchCharacteristicValue::class);
    }

    // Helpers

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return !$this->is_required;
        }

        return match ($this->data_type) {
            self::TYPE_NUMERIC => $this->validateNumeric($value),
            self::TYPE_DATE    => $this->validateDate($value),
            self::TYPE_BOOLEAN => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true),
            self::TYPE_TEXT    => $this->validateText((string) $value),
            default            => true,
        };
    }

    private function validateNumeric(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;

        if ($this->min_value !== null && $numericValue < (float) $this->min_value) {
            return false;
        }

        if ($this->max_value !== null && $numericValue > (float) $this->max_value) {
            return false;
        }

        return true;
    }

    private function validateDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return (bool) strtotime($value);
    }

    private function validateText(string $value): bool
    {
        if (empty($this->allowed_values)) {
            return true;
        }

        return in_array($value, $this->allowed_values, true);
    }
}
