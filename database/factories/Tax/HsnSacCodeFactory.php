<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Tax\HsnSacCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class HsnSacCodeFactory extends Factory
{
    protected $model = HsnSacCode::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('########'),
            'description' => fake()->sentence(),
            'gst_rate' => fake()->randomElement([0, 5, 12, 18, 28]),
            'type' => fake()->randomElement(['goods', 'services']),
        ];
    }
}
