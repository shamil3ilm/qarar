<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\CustomsTariffCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomsTariffCodeFactory extends Factory
{
    protected $model = CustomsTariffCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('########'),
            'description' => fake()->sentence(),
            'chapter' => fake()->numerify('##'),
            'heading' => fake()->numerify('####'),
            'subheading' => fake()->numerify('######'),
            'country_code' => fake()->randomElement(['SA', 'AE', 'IN']),
            'duty_rate_percent' => fake()->randomFloat(2, 0, 25),
            'specific_duty' => null,
            'specific_duty_unit' => null,
            'duty_type' => fake()->randomElement(['ad_valorem', 'specific', 'mixed']),
            'excise_rate' => null,
            'requires_license' => false,
            'is_prohibited' => false,
            'is_restricted' => false,
            'notes' => null,
            'is_active' => true,
        ];
    }
}
