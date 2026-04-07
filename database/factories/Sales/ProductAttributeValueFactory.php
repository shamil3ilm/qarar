<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ProductAttributeValue;
use App\Models\Sales\ProductAttribute;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'attribute_id' => ProductAttribute::factory(),
            'value_text' => fake()->optional(0.5)->word(),
            'value_number' => fake()->optional(0.3)->randomFloat(4, 1, 1000),
            'value_boolean' => null,
            'value_json' => null,
        ];
    }
}
