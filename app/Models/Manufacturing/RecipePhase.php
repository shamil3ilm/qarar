<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipePhase extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'recipe_id',
        'phase_number',
        'name',
        'operation_description',
        'resource_type',
        'resource_id',
        'duration_hours',
        'temperature',
        'pressure',
        'agitation_rpm',
    ];

    protected $casts = [
        'phase_number'   => 'integer',
        'duration_hours' => 'decimal:2',
        'temperature'    => 'decimal:2',
        'pressure'       => 'decimal:2',
        'agitation_rpm'  => 'integer',
        'resource_id'    => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(RecipeResource::class, 'recipe_phase_id');
    }
}
