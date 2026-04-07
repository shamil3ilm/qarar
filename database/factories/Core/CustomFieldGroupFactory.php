<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\CustomFieldGroup;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldGroupFactory extends Factory
{
    protected $model = CustomFieldGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'entity_type' => fake()->randomElement(['contact', 'product', 'invoice']),
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.3)->sentence(),
            'display_order' => fake()->numberBetween(1, 10),
            'is_collapsible' => true,
            'is_collapsed_default' => false,
            'is_active' => true,
        ];
    }
}
