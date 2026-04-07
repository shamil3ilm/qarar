<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class LoanSchedule extends Model
{
    use HasFactory;
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'outstanding_balance',
        'status',
        'paid_amount',
        'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_date' => 'date',
            'installment_number' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'schedule_id');
    }

    /**
     * Get the remaining amount for this schedule item.
     */
    public function getRemainingAmount(): float
    {
        return (float) ($this->total_amount - $this->paid_amount);
    }

    /**
     * Check if this installment is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return bccomp((string) $this->paid_amount, (string) $this->total_amount, 2) >= 0;
    }

    /**
     * Check if this installment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_PAID
            && $this->due_date->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }

    public function scopeDueBefore($query, string $date)
    {
        return $query->where('due_date', '<=', $date);
    }
}
