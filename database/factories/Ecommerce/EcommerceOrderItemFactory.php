<?php

declare(strict_types=1);

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceOrderItem;
use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class EcommerceOrderItemFactory extends Factory
{
    protected $model = EcommerceOrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => EcommerceOrder::factory(),
            'external_product_id' => fake()->numerify('EP-######'),
            'external_variant_id' => fake()->optional(0.5)->numerify('EV-######'),
            'sku' => strtoupper(fake()->bothify('SKU-####-???')),
            'name' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 10, 1000),
            'discount_amount' => 0,
            'tax_amount' => fake()->randomFloat(2, 0, 100),
            'total_amount' => fake()->randomFloat(2, 10, 1000),
            'product_id' => null,
            'fulfilled_quantity' => 0,
        ];
    }
}
