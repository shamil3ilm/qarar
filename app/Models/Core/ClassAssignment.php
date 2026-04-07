<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAssignment extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function classificationClass(): BelongsTo
    {
        return $this->belongsTo(ClassificationClass::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForObject(Builder $query, string $objectType, int $objectId): Builder
    {
        return $query->where('object_type', $objectType)->where('object_id', $objectId);
    }
}
