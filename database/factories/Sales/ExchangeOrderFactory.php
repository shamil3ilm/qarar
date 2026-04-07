<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ExchangeOrder;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeOrderFactory extends Factory
{
    protected $model = ExchangeOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'exchange_number' => 'EXO-' . fake()->unique()->numerify('######'),
            'sales_return_id' => null,
            'customer_id' => Contact::factory(),
            'original_total' => fake()->randomFloat(2, 100, 10000),
            'exchange_total' => fake()->randomFloat(2, 100, 10000),
            'price_difference' => fake()->randomFloat(2, -2000, 2000),
            'difference_resolution' => fake()->randomElement(['credit_note', 'additional_payment', 'none']),
            'new_invoice_id' => null,
            'new_sales_order_id' => null,
            'status' => fake()->randomElement(['draft', 'confirmed', 'fulfilled', 'cancelled']),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => null,
        ];
    }
}
