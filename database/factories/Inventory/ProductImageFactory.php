<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductImage;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'image_path' => 'products/' . fake()->uuid() . '.jpg',
            'thumbnail_path' => fake()->optional(0.5)->filePath(),
            'alt_text' => fake()->optional(0.5)->words(4, true),
            'title' => fake()->optional(0.5)->words(3, true),
            'width' => fake()->numberBetween(200, 2000),
            'height' => fake()->numberBetween(200, 2000),
            'file_size' => fake()->numberBetween(50000, 5000000),
            'image_type' => fake()->randomElement(['main', 'gallery', 'thumbnail']),
            'is_primary' => true,
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
