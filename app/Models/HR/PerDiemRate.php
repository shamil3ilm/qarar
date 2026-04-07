<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PerDiemRate extends Model
{
    use BelongsToOrganization;

    public const MEAL_TYPE_INCLUDED = 'included';
    public const MEAL_TYPE_SEPARATE = 'separate';

    protected $fillable = [
        'organization_id',
        'destination_country',
        'destination_city',
        'daily_allowance',
        'currency_code',
        'meal_allowance_type',
        'meal_breakfast',
        'meal_lunch',
        'meal_dinner',
        'mileage_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'daily_allowance' => 'decimal:4',
            'meal_breakfast'  => 'decimal:4',
            'meal_lunch'      => 'decimal:4',
            'meal_dinner'     => 'decimal:4',
            'mileage_rate'    => 'decimal:4',
            'is_active'       => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDestination(Builder $query, string $country, ?string $city = null): Builder
    {
        return $query->where('destination_country', $country)
            ->when($city !== null, fn (Builder $q) => $q->where('destination_city', $city));
    }

    // ---------------------------------------------------------------
    // Business methods
    // ---------------------------------------------------------------

    public function getTotalMealAllowance(): float
    {
        if ($this->meal_allowance_type === self::MEAL_TYPE_INCLUDED) {
            return 0.0;
        }

        return (float) ($this->meal_breakfast + $this->meal_lunch + $this->meal_dinner);
    }

    public function getDailyTotal(): float
    {
        return (float) $this->daily_allowance + $this->getTotalMealAllowance();
    }
}
