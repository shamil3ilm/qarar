<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelExpenseLine extends Model
{
    public const CATEGORY_FLIGHT    = 'flight';
    public const CATEGORY_HOTEL     = 'hotel';
    public const CATEGORY_MEAL      = 'meal';
    public const CATEGORY_TRANSPORT = 'transport';
    public const CATEGORY_PER_DIEM  = 'per_diem';
    public const CATEGORY_MILEAGE   = 'mileage';
    public const CATEGORY_VISA      = 'visa';
    public const CATEGORY_OTHER     = 'other';

    protected $fillable = [
        'claim_id',
        'expense_date',
        'expense_category',
        'description',
        'amount',
        'mileage_km',
        'currency_code',
        'exchange_rate',
        'amount_in_base_currency',
        'receipt_reference',
        'receipt_attached',
        'policy_limit',
        'within_policy',
    ];

    protected function casts(): array
    {
        return [
            'expense_date'             => 'date',
            'amount'                   => 'decimal:4',
            'mileage_km'               => 'decimal:2',
            'exchange_rate'            => 'decimal:6',
            'amount_in_base_currency'  => 'decimal:4',
            'policy_limit'             => 'decimal:4',
            'receipt_attached'         => 'boolean',
            'within_policy'            => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function claim(): BelongsTo
    {
        return $this->belongsTo(TravelExpenseClaim::class, 'claim_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeOutOfPolicy(Builder $query): Builder
    {
        return $query->where('within_policy', false);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('expense_category', $category);
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function getOverPolicyAmount(): float
    {
        if ($this->within_policy || $this->policy_limit === null) {
            return 0.0;
        }

        return (float) max(0, $this->amount_in_base_currency - $this->policy_limit);
    }
}
