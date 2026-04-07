<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\ProductVideo;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVideoFactory extends Factory
{
    protected $model = ProductVideo::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => fake()->words(4, true),
            'video_type' => fake()->randomElement(['youtube', 'vimeo', 'upload']),
            'video_url' => fake()->optional(0.7)->url(),
            'file_path' => fake()->optional(0.3)->filePath(),
            'thumbnail_path' => null,
            'duration_seconds' => fake()->optional(0.5)->numberBetween(30, 600),
            'is_primary' => true,
            'display_order' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }
}
