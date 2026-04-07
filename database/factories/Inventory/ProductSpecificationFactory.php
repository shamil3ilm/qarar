<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductSpecification;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductSpecificationFactory extends Factory
{
    protected $model = ProductSpecification::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'spec_group' => fake()->randomElement(['Physical', 'Technical', 'Performance']),
            'spec_name' => fake()->randomElement(['Weight', 'Dimensions', 'Material', 'Color', 'Power', 'Voltage']),
            'spec_value' => fake()->word(),
            'unit' => fake()->optional(0.5)->randomElement(['kg', 'cm', 'mm', 'W', 'V']),
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }
}
