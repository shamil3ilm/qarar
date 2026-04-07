<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FinancialCloseTask extends Model
{
    use HasUuid;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_BLOCKED     = 'blocked';
    public const STATUS_SKIPPED     = 'skipped';

    protected $fillable = [
        'financial_close_period_id',
        'template_task_id',
        'task_name',
        'description',
        'task_type',
        'assigned_to',
        'status',
        'due_date',
        'started_at',
        'completed_at',
        'completed_by',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
            'sort_order'   => 'integer',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialClosePeriod::class, 'financial_close_period_id');
    }

    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(FinancialCloseTemplateTask::class, 'template_task_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            FinancialCloseTask::class,
            'financial_close_task_dependencies',
            'financial_close_task_id',
            'depends_on_task_id'
        );
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            FinancialCloseTask::class,
            'financial_close_task_dependencies',
            'depends_on_task_id',
            'financial_close_task_id'
        );
    }
}
