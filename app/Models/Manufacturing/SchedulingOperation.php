<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulingOperation extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid;

    protected $fillable = [
        'organization_id',
        'scheduling_board_id',
        'work_order_id',
        'process_order_id',
        'work_center_id',
        'operation_number',
        'description',
        'planned_start',
        'planned_finish',
        'actual_start',
        'actual_finish',
        'duration_minutes',
        'setup_minutes',
        'teardown_minutes',
        'priority',
        'is_pinned',
        'is_fixed',
        'sequence_number',
    ];

    protected $casts = [
        'planned_start'   => 'datetime',
        'planned_finish'  => 'datetime',
        'actual_start'    => 'datetime',
        'actual_finish'   => 'datetime',
        'duration_minutes' => 'integer',
        'setup_minutes'   => 'integer',
        'teardown_minutes' => 'integer',
        'priority'        => 'integer',
        'is_pinned'       => 'boolean',
        'is_fixed'        => 'boolean',
        'sequence_number' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function board(): BelongsTo
    {
        return $this->belongsTo(SchedulingBoard::class, 'scheduling_board_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function processOrder(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function predecessorRelationships(): HasMany
    {
        return $this->hasMany(SchedulingPeggingRelationship::class, 'predecessor_operation_id');
    }

    public function successorRelationships(): HasMany
    {
        return $this->hasMany(SchedulingPeggingRelationship::class, 'successor_operation_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForWorkCenter(Builder $query, int $workCenterId): Builder
    {
        return $query->where('work_center_id', $workCenterId);
    }

    public function scopeBetween(Builder $query, string $dateFrom, string $dateTo): Builder
    {
        return $query->where('planned_start', '>=', $dateFrom)
            ->where('planned_finish', '<=', $dateTo);
    }

    public function scopeForBoard(Builder $query, int $boardId): Builder
    {
        return $query->where('scheduling_board_id', $boardId);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Total block duration including setup and teardown.
     */
    public function getTotalBlockMinutes(): int
    {
        return $this->duration_minutes + $this->setup_minutes + $this->teardown_minutes;
    }
}
