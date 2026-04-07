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

/**
 * Personnel Action — SAP PA40 equivalent.
 *
 * A single, atomic record that orchestrates all downstream HR changes
 * (position update, salary change, payroll notification, insurance update)
 * triggered by a hire / transfer / promotion / exit / rehire action.
 */
class PersonnelAction extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'personnel_actions';

    protected $guarded = ['id'];

    // Action types (mirrors SAP PA40 action type catalogue)
    public const TYPE_HIRE              = 'hire';
    public const TYPE_REHIRE            = 'rehire';
    public const TYPE_TRANSFER          = 'transfer';
    public const TYPE_PROMOTION         = 'promotion';
    public const TYPE_DEMOTION          = 'demotion';
    public const TYPE_EXIT              = 'exit';
    public const TYPE_LEAVE_OF_ABSENCE  = 'leave_of_absence';

    // Statuses
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_REVERSED  = 'reversed';

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'payload'        => 'array',
            'approved_at'    => 'datetime',
            'completed_at'   => 'datetime',
            'reversed_at'    => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PersonnelActionStep::class, 'personnel_action_id');
    }

    // ----------------------------------------------------------------
    // State helpers
    // ----------------------------------------------------------------

    public function canTransition(string $to): bool
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => $to === self::STATUS_SUBMITTED,
            self::STATUS_SUBMITTED => in_array($to, [self::STATUS_APPROVED, self::STATUS_REJECTED], true),
            self::STATUS_APPROVED  => $to === self::STATUS_COMPLETED,
            self::STATUS_COMPLETED => $to === self::STATUS_REVERSED,
            default                => false,
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
