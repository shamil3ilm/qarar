<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####-???')),
            'name' => fake()->words(3, true),
            'attribute_values' => ['color' => fake()->colorName(), 'size' => fake()->randomElement(['S', 'M', 'L', 'XL'])],
            'purchase_price' => fake()->randomFloat(4, 10, 5000),
            'selling_price' => fake()->randomFloat(4, 15, 7000),
            'cost_price' => fake()->randomFloat(4, 10, 5000),
            'barcode' => fake()->optional(0.5)->ean13(),
            'weight' => fake()->optional(0.5)->randomFloat(4, 0.1, 50),
            'dimensions' => null,
            'image_url' => null,
            'is_active' => true,
        ];
    }
}
