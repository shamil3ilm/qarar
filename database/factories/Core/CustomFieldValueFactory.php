<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\CustomFieldValue;
use App\Models\Core\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'field_definition_id' => CustomFieldDefinition::factory(),
            'entity_type' => fake()->randomElement(['contact', 'product', 'invoice']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'value_text' => fake()->optional(0.5)->words(3, true),
            'value_number' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_boolean' => null,
            'value_json' => null,
        ];
    }
}
