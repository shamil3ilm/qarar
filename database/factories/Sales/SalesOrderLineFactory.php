<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\SalesOrderLine;
use App\Models\Sales\SalesOrder;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => Product::factory(),
            'variant_id' => null,
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'quantity_delivered' => 0,
            'quantity_invoiced' => 0,
            'unit_id' => null,
            'unit_price' => fake()->randomFloat(4, 10, 5000),
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_category_id' => null,
            'tax_rate' => fake()->randomElement([0, 5, 10, 15]),
            'tax_amount' => fake()->randomFloat(4, 0, 500),
            'subtotal' => fake()->randomFloat(4, 10, 50000),
            'total' => fake()->randomFloat(4, 10, 55000),
            'warehouse_id' => null,
            'line_order' => fake()->numberBetween(1, 20),
        ];
    }
}
