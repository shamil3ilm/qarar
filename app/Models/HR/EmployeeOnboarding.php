<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOnboarding extends Model
{
    use HasUuid;

    protected $table = 'hr_onboardings';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'template_type',
        'status',
        'started_date',
        'target_completion_date',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_date'           => 'date',
            'target_completion_date' => 'date',
            'completed_at'           => 'datetime',
        ];
    }

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class, 'onboarding_id')->orderBy('sort_order');
    }

    public function pendingTasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class, 'onboarding_id')
            ->whereIn('status', ['pending', 'in_progress']);
    }

    public function isComplete(): bool
    {
        return !$this->tasks()
            ->where('is_required', true)
            ->whereNotIn('status', ['done', 'skipped'])
            ->exists();
    }
}
