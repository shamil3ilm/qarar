<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => fake()->unique()->slug(2),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.5)->sentence(),
            'is_enabled' => true,
            'rollout_type' => fake()->randomElement(['all', 'percentage', 'specific']),
            'rollout_percentage' => fake()->numberBetween(0, 100),
            'specific_organization_ids' => null,
            'specific_subscription_plans' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_by' => null,
        ];
    }
}