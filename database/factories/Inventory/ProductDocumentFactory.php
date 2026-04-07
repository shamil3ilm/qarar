<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductDocument;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductDocumentFactory extends Factory
{
    protected $model = ProductDocument::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(3, true),
            'file_path' => 'product-docs/' . fake()->uuid() . '.pdf',
            'file_type' => fake()->randomElement(['pdf', 'doc', 'xlsx']),
            'file_size' => fake()->numberBetween(1024, 10485760),
            'document_type' => fake()->randomElement(['manual', 'datasheet', 'warranty', 'safety']),
            'language' => fake()->randomElement(['en', 'ar']),
            'is_public' => true,
            'display_order' => fake()->numberBetween(1, 10),
        ];
    }
}
