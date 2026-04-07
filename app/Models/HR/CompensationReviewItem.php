<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationReviewItem extends Model
{
    use HasFactory;

    public const ADJUSTMENT_MERIT             = 'merit';
    public const ADJUSTMENT_PROMOTION         = 'promotion';
    public const ADJUSTMENT_MARKET_ADJUSTMENT = 'market_adjustment';
    public const ADJUSTMENT_EQUITY            = 'equity';

    public const STATUS_PENDING     = 'pending';
    public const STATUS_RECOMMENDED = 'recommended';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_REJECTED    = 'rejected';

    protected $fillable = [
        'review_id',
        'employee_id',
        'current_salary',
        'proposed_salary',
        'increase_amount',
        'increase_percentage',
        'adjustment_type',
        'justification',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'current_salary'      => 'decimal:4',
            'proposed_salary'     => 'decimal:4',
            'increase_amount'     => 'decimal:4',
            'increase_percentage' => 'decimal:2',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(CompensationReview::class, 'review_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recalculateIncrease(): void
    {
        if ($this->proposed_salary !== null && $this->current_salary > 0) {
            $this->increase_amount     = (float) $this->proposed_salary - (float) $this->current_salary;
            $this->increase_percentage = round(
                ((float) $this->increase_amount / (float) $this->current_salary) * 100,
                2
            );
        }
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRecommended($query)
    {
        return $query->where('status', self::STATUS_RECOMMENDED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
