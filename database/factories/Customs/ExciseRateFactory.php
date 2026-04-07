<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\ExciseRate;
use App\Models\Customs\ExciseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExciseRateFactory extends Factory
{
    protected $model = ExciseRate::class;

    public function definition(): array
    {
        return [
            'excise_category_id' => ExciseCategory::factory(),
            'name' => fake()->words(3, true),
            'rate_type' => fake()->randomElement(['ad_valorem', 'specific']),
            'rate_percent' => fake()->randomFloat(4, 5, 100),
            'specific_amount' => null,
            'specific_unit' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED']),
            'country_code' => fake()->randomElement(['SA', 'AE']),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
