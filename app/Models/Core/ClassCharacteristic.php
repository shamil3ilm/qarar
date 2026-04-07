<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassCharacteristic extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_required'    => 'boolean',
            'is_searchable'  => 'boolean',
            'min_value'      => 'decimal:4',
            'max_value'      => 'decimal:4',
            'allowed_values' => 'array',
            'sort_order'     => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function classificationClass(): BelongsTo
    {
        return $this->belongsTo(ClassificationClass::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ClassCharacteristicValue::class);
    }
}
