<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\BomOperation;
use App\Models\Manufacturing\BomTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomOperationFactory extends Factory
{
    protected $model = BomOperation::class;

    public function definition(): array
    {
        return [
            'bom_template_id' => BomTemplate::factory(),
            'name' => fake()->randomElement(['Cutting', 'Assembly', 'Welding', 'Painting', 'Testing', 'Packaging']),
            'description' => fake()->optional(0.5)->sentence(),
            'instructions' => fake()->optional(0.3)->paragraph(),
            'sequence' => fake()->numberBetween(1, 10),
            'estimated_minutes' => fake()->numberBetween(10, 480),
            'labor_cost_per_hour' => fake()->randomFloat(4, 15, 150),
            'workstation' => fake()->optional(0.5)->word(),
            'required_skills' => fake()->optional(0.3)->words(3),
            'is_subcontracted' => false,
        ];
    }
}
