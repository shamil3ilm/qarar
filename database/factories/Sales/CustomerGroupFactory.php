<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CustomerGroup;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Retail', 'Wholesale', 'Distributor', 'VIP', 'Walk-In']),
            'code' => strtoupper(fake()->unique()->lexify('GRP-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'default_discount_percent' => fake()->randomFloat(2, 0, 25),
            'credit_limit' => fake()->optional(0.5)->randomFloat(4, 5000, 500000),
            'payment_terms_days' => fake()->randomElement([0, 7, 15, 30, 45, 60]),
            'tax_exempt' => false,
            'wholesale' => fake()->boolean(30),
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 10),
        ];
    }
}
