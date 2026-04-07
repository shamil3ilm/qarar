<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Tax\TaxRate;
use App\Models\Tax\TaxCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'tax_category_id' => TaxCategory::factory(),
            'name' => fake()->randomElement(['VAT 15%', 'VAT 5%', 'GST 18%', 'GST 12%', 'Zero Rate']),
            'rate' => fake()->randomElement([0, 5, 10, 12, 15, 18, 28]),
            'country_code' => fake()->randomElement(['SA', 'AE', 'IN']),
            'effective_from' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
