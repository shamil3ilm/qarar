<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\OrganizationCurrency;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationCurrencyFactory extends Factory
{
    protected $model = OrganizationCurrency::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD', 'EUR']),
            'is_base_currency' => false,
            'is_active' => true,
            'exchange_gain_account_id' => null,
            'exchange_loss_account_id' => null,
            'rounding_account_id' => null,
            'rounding_precision' => '0.0100',
            'rounding_method' => fake()->randomElement(['round', 'floor', 'ceil']),
        ];
    }
}