<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingTask extends Model
{
    protected $table = 'hr_onboarding_tasks';

    protected $fillable = [
        'onboarding_id',
        'title',
        'description',
        'category',
        'due_date',
        'status',
        'is_required',
        'sort_order',
        'assigned_to',
        'completed_by',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'completed_at' => 'datetime',
            'is_required'  => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_SKIPPED     = 'skipped';

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'onboarding_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
