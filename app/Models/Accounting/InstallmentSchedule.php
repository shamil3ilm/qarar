<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends Model
{
    use HasUuid;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PARTIAL  = 'partial';
    public const STATUS_PAID     = 'paid';
    public const STATUS_OVERDUE  = 'overdue';
    public const STATUS_WAIVED   = 'waived';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:4',
            'paid_amount'        => 'decimal:4',
            'due_date'           => 'date',
            'paid_date'          => 'date',
            'installment_number' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function remainingAmount(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 4);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE
            || ($this->status === self::STATUS_PENDING && $this->due_date->isPast());
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_OVERDUE]);
    }

    public function scopeDueBefore($query, string $date)
    {
        return $query->where('due_date', '<=', $date);
    }
}
