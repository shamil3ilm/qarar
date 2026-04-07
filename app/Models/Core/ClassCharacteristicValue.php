<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCharacteristicValue extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'numeric_value'  => 'decimal:4',
            'date_value'     => 'date',
            'boolean_value'  => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(ClassCharacteristic::class, 'class_characteristic_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForObject(Builder $query, string $objectType, int $objectId): Builder
    {
        return $query->where('object_type', $objectType)->where('object_id', $objectId);
    }
}
