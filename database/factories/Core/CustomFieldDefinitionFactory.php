<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\CustomFieldDefinition;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'entity_type' => fake()->randomElement(['contact', 'product', 'invoice']),
            'field_name' => fake()->unique()->slug(2),
            'field_label' => fake()->words(3, true),
            'field_type' => fake()->randomElement(['text', 'number', 'date', 'select', 'boolean', 'textarea']),
            'description' => fake()->optional(0.3)->sentence(),
            'options' => null,
            'validation' => null,
            'default_value' => null,
            'placeholder' => fake()->optional(0.3)->words(3, true),
            'display_order' => fake()->numberBetween(1, 20),
            'field_group' => null,
            'is_required' => false,
            'is_unique' => false,
            'is_searchable' => true,
            'is_filterable' => false,
            'show_in_list' => true,
            'show_in_form' => true,
            'is_active' => true,
        ];
    }
}
