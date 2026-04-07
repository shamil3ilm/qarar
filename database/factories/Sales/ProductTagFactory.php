<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ProductTag;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTagFactory extends Factory
{
    protected $model = ProductTag::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['New Arrival', 'Best Seller', 'Sale', 'Featured', 'Trending']),
            'slug' => fake()->unique()->slug(2),
            'color' => fake()->hexColor(),
            'tag_group' => fake()->optional(0.3)->randomElement(['marketing', 'seasonal', 'category']),
            'description' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }
}
