<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\QuickSaleTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuickSaleTemplateFactory extends Factory
{
    protected $model = QuickSaleTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'description' => fake()->optional(0.3)->sentence(),
            'default_items' => [['product_id' => 1, 'quantity' => 1]],
            'default_customer_id' => null,
            'default_payment_method' => fake()->optional(0.5)->randomElement(['cash', 'card', 'bank_transfer']),
            'is_active' => true,
        ];
    }
}
