<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\Billing\BillingCredit;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingCreditFactory extends Factory
{
    protected $model = BillingCredit::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'balance' => fake()->randomFloat(2, 0, 5000),
            'total_credited' => fake()->randomFloat(2, 0, 10000),
            'total_used' => fake()->randomFloat(2, 0, 5000),
            'currency_code' => 'USD',
        ];
    }
}