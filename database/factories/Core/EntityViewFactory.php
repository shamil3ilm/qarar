<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\EntityView;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityViewFactory extends Factory
{
    protected $model = EntityView::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'entity_type' => fake()->randomElement(['invoice', 'contact', 'product']),
            'entity_id' => fake()->numberBetween(1, 1000),
            'entity_name' => fake()->words(3, true),
            'viewed_at' => now(),
        ];
    }
}
