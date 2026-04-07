<?php

declare(strict_types=1);

namespace App\Models\Projects;

use App\Models\Concerns\HasUuid;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkActivity extends Model
{
    use HasFactory, HasUuid;

    public const TYPE_INTERNAL     = 'internal';
    public const TYPE_EXTERNAL     = 'external';
    public const TYPE_GENERAL_COST = 'general_cost';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'project_id',
        'wbs_element_id',
        'activity_number',
        'description',
        'activity_type',
        'work_center_id',
        'planned_work',
        'actual_work',
        'earliest_start',
        'latest_start',
        'earliest_finish',
        'latest_finish',
        'float_days',
        'status',
    ];

    protected $casts = [
        'planned_work'   => 'decimal:2',
        'actual_work'    => 'decimal:2',
        'float_days'     => 'decimal:2',
        'earliest_start' => 'date',
        'latest_start'   => 'date',
        'earliest_finish' => 'date',
        'latest_finish'  => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function wbsElement(): BelongsTo
    {
        return $this->belongsTo(WbsElement::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(NetworkActivityRelationship::class, 'predecessor_activity_id');
    }

    /**
     * Activities that must finish before this one starts (predecessors via pivot).
     */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(
            NetworkActivity::class,
            'network_activity_relationships',
            'successor_activity_id',
            'predecessor_activity_id'
        )->withPivot(['relationship_type', 'lag_days'])->withTimestamps();
    }

    /**
     * Activities that can start after this one (successors via pivot).
     */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            NetworkActivity::class,
            'network_activity_relationships',
            'predecessor_activity_id',
            'successor_activity_id'
        )->withPivot(['relationship_type', 'lag_days'])->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', self::STATUS_NOT_STARTED);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOnCriticalPath(): bool
    {
        return (float) $this->float_days === 0.0;
    }

    public function getWorkVariance(): float
    {
        return (float) $this->actual_work - (float) $this->planned_work;
    }
}
