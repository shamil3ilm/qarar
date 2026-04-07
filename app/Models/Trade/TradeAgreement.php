<?php

declare(strict_types=1);

namespace App\Models\Trade;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class TradeAgreement extends Model
{
    use HasFactory;
    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'description',
        'member_countries',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'member_countries' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function preferentialDutyRates(): HasMany
    {
        return $this->hasMany(PreferentialDutyRate::class, 'trade_agreement_id');
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

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->whereJsonContains('member_countries', $countryCode);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCountryMember(string $countryCode): bool
    {
        return in_array($countryCode, $this->member_countries ?? []);
    }

    public function isCurrentlyEffective(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && $this->effective_from <= $today
            && ($this->effective_to === null || $this->effective_to >= $today);
    }
}
