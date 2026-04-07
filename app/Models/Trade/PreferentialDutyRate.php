<?php

declare(strict_types=1);

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class PreferentialDutyRate extends Model
{
    use HasFactory;
    protected $fillable = [
        'trade_agreement_id',
        'tariff_code',
        'origin_country',
        'destination_country',
        'preferential_rate',
        'normal_rate',
        'rule_of_origin',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'preferential_rate' => 'decimal:4',
            'normal_rate' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function tradeAgreement(): BelongsTo
    {
        return $this->belongsTo(TradeAgreement::class, 'trade_agreement_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective(Builder $query, ?string $date = null): Builder
    {
        $date = $date ?? now()->toDateString();

        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    public function scopeForTariffCode(Builder $query, string $tariffCode): Builder
    {
        return $query->where('tariff_code', $tariffCode);
    }

    public function scopeForRoute(Builder $query, string $originCountry, string $destinationCountry): Builder
    {
        return $query->where('origin_country', $originCountry)
            ->where('destination_country', $destinationCountry);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getDutySaving(): float
    {
        if ($this->normal_rate === null) {
            return 0.0;
        }

        return round((float) $this->normal_rate - (float) $this->preferential_rate, 4);
    }
}
