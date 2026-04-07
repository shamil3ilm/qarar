<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LtpSimulation extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED  = 'archived';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'planning_horizon_from',
        'planning_horizon_to',
        'status',
        'mrp_run_id',
        'created_by',
        'run_at',
    ];

    protected $casts = [
        'planning_horizon_from' => 'date',
        'planning_horizon_to'   => 'date',
        'run_at'                => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function mrpRun(): BelongsTo
    {
        return $this->belongsTo(MrpRun::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function plannedOrders(): HasMany
    {
        return $this->hasMany(LtpPlannedOrder::class);
    }

    public function capacityRequirements(): HasMany
    {
        return $this->hasMany(LtpCapacityRequirement::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeRun(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_COMPLETED], true);
    }
}
