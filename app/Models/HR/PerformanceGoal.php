<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceGoal extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'organization_id',
        'employee_id',
        'appraisal_cycle_id',
        'title',
        'description',
        'target_date',
        'weight_percent',
        'status',
        'progress_percent',
        'self_rating',
        'manager_rating',
        'self_comments',
        'manager_comments',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'weight_percent' => 'float',
        'progress_percent' => 'integer',
        'self_rating' => 'integer',
        'manager_rating' => 'integer',
    ];

    // ---------------------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PerformanceGoalUpdate::class)->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForCycle(Builder $query, int $cycleId): Builder
    {
        return $query->where('appraisal_cycle_id', $cycleId);
    }

    // ---------------------------------------------------------------------------
    // Business logic
    // ---------------------------------------------------------------------------

    /**
     * Record a progress update for this goal and update the current progress_percent.
     */
    public function updateProgress(int $percent, string $notes, int $userId): PerformanceGoalUpdate
    {
        $this->progress_percent = max(0, min(100, $percent));

        if ($this->progress_percent === 100 && $this->status === self::STATUS_ACTIVE) {
            $this->status = self::STATUS_COMPLETED;
        }

        $this->save();

        return $this->updates()->create([
            'updated_by' => $userId,
            'progress_percent' => $this->progress_percent,
            'notes' => $notes ?: null,
        ]);
    }
}
