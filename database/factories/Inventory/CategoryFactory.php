<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\Category;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.5)->sentence(),
            'image_url' => null,
            'level' => 1,
            'path' => null,
            'is_active' => true,
        ];
    }
}
