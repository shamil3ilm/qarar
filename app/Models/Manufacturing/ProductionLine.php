<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionLine extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'work_center_id',
        'capacity_per_hour',
        'unit_id',
        'is_active',
    ];

    protected $casts = [
        'capacity_per_hour' => 'decimal:4',
        'is_active'         => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RepetitiveMfgSchedule::class, 'production_line_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
