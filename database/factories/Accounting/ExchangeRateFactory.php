<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\ExchangeRate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'from_currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'to_currency' => 'SAR',
            'rate' => fake()->randomFloat(8, 0.1, 10.0),
            'rate_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}