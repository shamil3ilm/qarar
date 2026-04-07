<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\FeatureFlag;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'feature' => fake()->unique()->slug(2),
            'is_enabled' => true,
            'config' => null,
            'enabled_at' => now(),
            'disabled_at' => null,
        ];
    }
}
