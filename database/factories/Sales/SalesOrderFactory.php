<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'order_number' => 'SO-' . fake()->unique()->numerify('######'),
            'quotation_id' => null,
            'customer_id' => Contact::factory(),
            'customer_name' => fake()->company(),
            'customer_email' => fake()->safeEmail(),
            'billing_address' => fake()->address(),
            'shipping_address' => fake()->address(),
            'order_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'expected_delivery_date' => fake()->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'delivery_date' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'exchange_rate' => '1.00000000',
            'subtotal' => fake()->randomFloat(4, 100, 50000),
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_amount' => fake()->randomFloat(4, 0, 5000),
            'total' => fake()->randomFloat(4, 100, 55000),
            'status' => fake()->randomElement(['draft', 'confirmed', 'processing', 'delivered', 'invoiced', 'cancelled']),
            'salesperson_id' => null,
            'warehouse_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'delivery_instructions' => null,
            'reference' => fake()->optional(0.3)->bothify('REF-####'),
            'version' => 1,
            'created_by' => null,
        ];
    }
}
