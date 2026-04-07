<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\PaymentGateway;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company() . ' Gateway',
            'provider' => fake()->randomElement(['stripe', 'tap', 'moyasar', 'hyperpay']),
            'credentials' => null,
            'settings' => null,
            'mode' => fake()->randomElement(['test', 'live']),
            'is_active' => true,
            'is_default' => false,
            'supported_currencies' => ['SAR', 'AED', 'USD'],
            'supported_methods' => ['card', 'bank'],
        ];
    }
}
