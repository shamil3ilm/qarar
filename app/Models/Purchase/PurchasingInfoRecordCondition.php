<?php

declare(strict_types=1);

namespace App\Models\Purchase;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasingInfoRecordCondition extends Model
{
    use HasUuid;

    protected $fillable = [
        'organization_id',
        'purchasing_info_record_id',
        'valid_from',
        'valid_to',
        'net_price',
        'price_unit',
        'currency_code',
        'discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'      => 'date',
            'valid_to'        => 'date',
            'net_price'       => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'is_active'       => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function infoRecord(): BelongsTo
    {
        return $this->belongsTo(PurchasingInfoRecord::class, 'purchasing_info_record_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Conditions that are valid on the given date.
     */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            });
    }
}
