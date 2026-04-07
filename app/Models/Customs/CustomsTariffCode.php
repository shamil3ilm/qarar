<?php

declare(strict_types=1);

namespace App\Models\Customs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class CustomsTariffCode extends Model
{
    use HasFactory;
    public const DUTY_TYPE_AD_VALOREM = 'ad_valorem';
    public const DUTY_TYPE_SPECIFIC = 'specific';
    public const DUTY_TYPE_COMPOSITE = 'composite';
    public const DUTY_TYPE_MIXED = 'mixed';

    protected $fillable = [
        'code',
        'description',
        'chapter',
        'heading',
        'subheading',
        'country_code',
        'duty_rate_percent',
        'specific_duty',
        'specific_duty_unit',
        'duty_type',
        'excise_rate',
        'requires_license',
        'is_prohibited',
        'is_restricted',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duty_rate_percent' => 'decimal:4',
            'specific_duty' => 'decimal:4',
            'excise_rate' => 'decimal:4',
            'requires_license' => 'boolean',
            'is_prohibited' => 'boolean',
            'is_restricted' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function declarationItems(): HasMany
    {
        return $this->hasMany(CustomsDeclarationItem::class, 'tariff_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeInternational(Builder $query): Builder
    {
        return $query->whereNull('country_code');
    }

    public function scopeForChapter(Builder $query, string $chapter): Builder
    {
        return $query->where('chapter', $chapter);
    }

    public function scopeSearchByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', 'like', "{$code}%");
    }

    public function scopeSearchByDescription(Builder $query, string $term): Builder
    {
        return $query->where('description', 'like', "%{$term}%");
    }

    public function scopeNotProhibited(Builder $query): Builder
    {
        return $query->where('is_prohibited', false);
    }

    public function calculateDuty(float $assessableValue, float $quantity = 0): float
    {
        $duty = 0.0;

        if (in_array($this->duty_type, [self::DUTY_TYPE_AD_VALOREM, self::DUTY_TYPE_COMPOSITE, self::DUTY_TYPE_MIXED])) {
            $duty += $assessableValue * ((float) $this->duty_rate_percent / 100);
        }

        if (in_array($this->duty_type, [self::DUTY_TYPE_SPECIFIC, self::DUTY_TYPE_COMPOSITE, self::DUTY_TYPE_MIXED]) && $this->specific_duty) {
            $duty += $quantity * (float) $this->specific_duty;
        }

        return round($duty, 4);
    }
}
