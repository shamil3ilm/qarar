<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\Account;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceObligation extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const METHOD_POINT_IN_TIME = 'point_in_time';
    public const METHOD_OVER_TIME = 'over_time';
    public const METHOD_MILESTONE = 'milestone';

    protected $fillable = [
        'revenue_contract_id',
        'description',
        'standalone_selling_price',
        'allocated_transaction_price',
        'recognition_method',
        'status',
        'completion_percentage',
        'recognized_amount',
        'deferred_amount',
        'revenue_account_id',
        'deferred_account_id',
    ];

    protected function casts(): array
    {
        return [
            'standalone_selling_price'    => 'decimal:4',
            'allocated_transaction_price' => 'decimal:4',
            'completion_percentage'       => 'decimal:2',
            'recognized_amount'           => 'decimal:4',
            'deferred_amount'             => 'decimal:4',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RevenueContract::class, 'revenue_contract_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function deferredAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deferred_account_id');
    }

    public function recognitionEvents(): HasMany
    {
        return $this->hasMany(RevenueRecognitionEvent::class, 'performance_obligation_id')
            ->orderBy('event_date');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getRemainingAmount(): float
    {
        return (float) bcsub(
            (string) $this->allocated_transaction_price,
            (string) $this->recognized_amount,
            4
        );
    }

    public function isFullyRecognized(): bool
    {
        return bccomp(
            (string) $this->recognized_amount,
            (string) $this->allocated_transaction_price,
            4
        ) >= 0;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }
}
