<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PriceList;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Price List',
            'code' => strtoupper(fake()->unique()->lexify('PL-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'type' => fake()->randomElement(['selling', 'buying']),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'is_default' => false,
            'is_tax_inclusive' => false,
            'valid_from' => fake()->optional(0.5)->dateTimeBetween('-6 months', 'now'),
            'valid_until' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'customer_group_id' => null,
            'priority' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
