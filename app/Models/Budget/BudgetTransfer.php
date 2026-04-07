<?php

declare(strict_types=1);

namespace App\Models\Budget;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetTransfer extends Model
{
    use BelongsToOrganization;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'budget_transfers';

    protected $guarded = ['id'];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_POSTED    = 'posted';

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'approved_at' => 'datetime',
            'posted_at'   => 'datetime',
        ];
    }

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    public function fromBudget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'from_budget_id');
    }

    public function fromBudgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'from_budget_line_id');
    }

    public function toBudget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'to_budget_id');
    }

    public function toBudgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'to_budget_line_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ----------------------------------------------------------------
    // State helpers
    // ----------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function canTransition(string $to): bool
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => $to === self::STATUS_SUBMITTED,
            self::STATUS_SUBMITTED => in_array($to, [self::STATUS_APPROVED, self::STATUS_REJECTED], true),
            self::STATUS_APPROVED  => $to === self::STATUS_POSTED,
            default                => false,
        };
    }
}
