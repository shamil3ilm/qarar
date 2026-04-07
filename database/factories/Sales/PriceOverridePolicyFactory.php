<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\PriceOverridePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceOverridePolicyFactory extends Factory
{
    protected $model = PriceOverridePolicy::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Policy',
            'description' => fake()->sentence(),
            'allow_price_change' => true,
            'allow_discount' => true,
            'allow_markup' => false,
            'allow_free_item' => false,
            'max_discount_percent' => fake()->randomFloat(2, 5, 50),
            'max_markup_percent' => fake()->randomFloat(2, 0, 30),
            'max_discount_amount' => fake()->optional(0.5)->randomFloat(2, 100, 5000),
            'min_price_percent' => fake()->optional(0.4)->randomFloat(2, 50, 90),
            'max_total_discount_percent' => fake()->optional(0.4)->randomFloat(2, 10, 40),
            'requires_approval' => fake()->boolean(40),
            'approval_threshold_percent' => fake()->optional(0.4)->randomFloat(2, 10, 30),
            'approval_threshold_amount' => fake()->optional(0.4)->randomFloat(2, 100, 2000),
            'requires_reason' => true,
            'applies_to' => fake()->randomElement(['all', 'roles', 'users', 'branches']),
            'applicable_role_ids' => null,
            'applicable_user_ids' => null,
            'applicable_branch_ids' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function requiresApproval(): static
    {
        return $this->state(fn () => [
            'requires_approval' => true,
            'approval_threshold_percent' => 15.00,
        ]);
    }

    public function restrictive(): static
    {
        return $this->state(fn () => [
            'max_discount_percent' => 5.00,
            'allow_markup' => false,
            'allow_free_item' => false,
            'requires_approval' => true,
            'requires_reason' => true,
        ]);
    }

    public function permissive(): static
    {
        return $this->state(fn () => [
            'max_discount_percent' => 100.00,
            'allow_markup' => true,
            'allow_free_item' => true,
            'requires_approval' => false,
        ]);
    }
}
