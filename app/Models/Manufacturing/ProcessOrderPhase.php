<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessOrderPhase extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    protected $fillable = [
        'process_order_id',
        'recipe_phase_id',
        'phase_number',
        'name',
        'status',
        'started_at',
        'completed_at',
        'actual_temperature',
        'actual_pressure',
        'actual_duration_minutes',
        'operator_notes',
    ];

    protected $casts = [
        'phase_number'            => 'integer',
        'started_at'              => 'datetime',
        'completed_at'            => 'datetime',
        'actual_temperature'      => 'decimal:2',
        'actual_pressure'         => 'decimal:2',
        'actual_duration_minutes' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function processOrder(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class);
    }

    public function recipePhase(): BelongsTo
    {
        return $this->belongsTo(RecipePhase::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
