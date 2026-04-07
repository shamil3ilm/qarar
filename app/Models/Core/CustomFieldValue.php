<?php

declare(strict_types=1);

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CustomFieldValue extends Model
{
    use HasFactory;
    protected $fillable = [
        'field_definition_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_date',
        'value_datetime',
        'value_boolean',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:6',
            'value_date' => 'date',
            'value_datetime' => 'datetime',
            'value_boolean' => 'boolean',
            'value_json' => 'array',
        ];
    }

    // Relationships

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_definition_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopeForEntity($query, string $entityType, $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    public function scopeForDefinition($query, int $definitionId)
    {
        return $query->where('field_definition_id', $definitionId);
    }

    // Helpers

    /**
     * Get the resolved value based on the field type.
     */
    public function getResolvedValue(): mixed
    {
        $column = $this->definition->getValueColumn();

        return $this->{$column};
    }

    /**
     * Set the value in the appropriate column based on field type.
     */
    public function setResolvedValue(mixed $value): void
    {
        // Reset all value columns
        $this->value_text = null;
        $this->value_number = null;
        $this->value_date = null;
        $this->value_datetime = null;
        $this->value_boolean = null;
        $this->value_json = null;

        $column = $this->definition->getValueColumn();
        $this->{$column} = $value;
    }
}
