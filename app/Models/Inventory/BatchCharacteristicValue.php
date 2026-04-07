<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchCharacteristicValue extends Model
{
    use BelongsToOrganization, HasUuid;

    protected $fillable = [
        'organization_id',
        'inventory_batch_id',
        'batch_characteristic_id',
        'text_value',
        'numeric_value',
        'date_value',
        'boolean_value',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
            'date_value'    => 'date',
            'boolean_value' => 'boolean',
        ];
    }

    // Relationships

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(BatchCharacteristic::class, 'batch_characteristic_id');
    }

    // Helpers

    public function getValue(): mixed
    {
        $characteristic = $this->characteristic;

        if ($characteristic === null) {
            return null;
        }

        return match ($characteristic->data_type) {
            BatchCharacteristic::TYPE_NUMERIC => $this->numeric_value,
            BatchCharacteristic::TYPE_DATE    => $this->date_value,
            BatchCharacteristic::TYPE_BOOLEAN => $this->boolean_value,
            BatchCharacteristic::TYPE_TEXT    => $this->text_value,
            default                           => $this->text_value,
        };
    }
}
