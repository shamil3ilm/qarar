<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->country() . ' Dollar',
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', '₹', 'ر.س']),
            'decimal_places' => fake()->randomElement([0, 2, 3]),
            'is_active' => true,
        ];
    }
}