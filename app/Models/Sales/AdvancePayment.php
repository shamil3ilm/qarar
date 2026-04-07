<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Concerns\HasUuid;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvancePayment extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    public const STATUS_DRAFT            = 'draft';
    public const STATUS_RECEIVED         = 'received';
    public const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    public const STATUS_FULLY_APPLIED    = 'fully_applied';
    public const STATUS_REFUNDED         = 'refunded';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'advance_number',
        'advance_date',
        'amount',
        'applied_amount',
        'balance_amount',
        'currency_code',
        'payment_method',
        'reference',
        'bank_account_id',
        'status',
        'notes',
        'created_by',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'advance_date'   => 'date',
            'amount'         => 'decimal:4',
            'applied_amount' => 'decimal:4',
            'balance_amount' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdvancePaymentApplication::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Advances that still have a balance available for application.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_APPLIED,
        ]);
    }
}
