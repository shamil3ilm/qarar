<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OffCyclePayrollRun extends Model
{
    use BelongsToOrganization, HasUuid, SoftDeletes;

    public const RUN_TYPE_BONUS = 'bonus';
    public const RUN_TYPE_TERMINATION = 'termination';
    public const RUN_TYPE_CORRECTION = 'correction';
    public const RUN_TYPE_ADVANCE_RECOVERY = 'advance_recovery';
    public const RUN_TYPE_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'run_type',
        'run_name',
        'run_date',
        'status',
        'employee_count',
        'total_gross',
        'total_net',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date'     => 'date',
            'processed_at' => 'datetime',
            'total_gross'  => 'decimal:4',
            'total_net'    => 'decimal:4',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OffCyclePayrollItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canProcess(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PROCESSING], true);
    }
}
