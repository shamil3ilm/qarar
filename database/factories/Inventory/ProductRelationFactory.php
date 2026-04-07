<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductRelation;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRelationFactory extends Factory
{
    protected $model = ProductRelation::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'related_product_id' => Product::factory(),
            'relation_type' => fake()->randomElement(['cross_sell', 'up_sell', 'accessory', 'substitute', 'complement']),
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
