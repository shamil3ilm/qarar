<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ProductTagAssignment;
use App\Models\Sales\ProductTag;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTagAssignmentFactory extends Factory
{
    protected $model = ProductTagAssignment::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'tag_id' => ProductTag::factory(),
        ];
    }
}
