<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ProductAttribute;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Color', 'Size', 'Material', 'Brand', 'Style']),
            'code' => fake()->unique()->slug(2),
            'type' => fake()->randomElement(['select', 'text', 'number', 'color', 'boolean']),
            'options' => fake()->optional(0.5)->words(5),
            'unit' => null,
            'is_filterable' => true,
            'is_comparable' => true,
            'is_visible' => true,
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
