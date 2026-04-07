<?php

declare(strict_types=1);

namespace App\Models\Customs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class ExciseRate extends Model
{
    use HasFactory;
    public const RATE_TYPE_PERCENTAGE = 'percentage';
    public const RATE_TYPE_SPECIFIC = 'specific';
    public const RATE_TYPE_COMPOSITE = 'composite';

    protected $fillable = [
        'excise_category_id',
        'name',
        'rate_type',
        'rate_percent',
        'specific_amount',
        'specific_unit',
        'currency_code',
        'country_code',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:4',
            'specific_amount' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExciseCategory::class, 'excise_category_id');
    }

    public function productMappings()
    {
        return $this->hasMany(ProductExciseMapping::class, 'excise_rate_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, ?string $date = null): Builder
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
        return $query->where('country_code', $countryCode);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function calculateExcise(float $value, float $quantity = 0): float
    {
        $excise = 0.0;

        if (in_array($this->rate_type, [self::RATE_TYPE_PERCENTAGE, self::RATE_TYPE_COMPOSITE]) && $this->rate_percent) {
            $excise += $value * ((float) $this->rate_percent / 100);
        }

        if (in_array($this->rate_type, [self::RATE_TYPE_SPECIFIC, self::RATE_TYPE_COMPOSITE]) && $this->specific_amount) {
            $excise += $quantity * (float) $this->specific_amount;
        }

        return round($excise, 4);
    }

    public function isCurrentlyEffective(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && $this->effective_from <= $today
            && ($this->effective_to === null || $this->effective_to >= $today);
    }
}
