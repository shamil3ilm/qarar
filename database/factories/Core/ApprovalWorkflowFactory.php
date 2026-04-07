<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ApprovalWorkflow;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Approval',
            'code' => fake()->unique()->slug(2),
            'description' => fake()->optional(0.5)->sentence(),
            'approvable_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Purchase\PurchaseOrder']),
            'min_amount' => fake()->optional(0.5)->randomFloat(4, 0, 1000),
            'max_amount' => fake()->optional(0.5)->randomFloat(4, 1000, 1000000),
            'conditions' => null,
            'is_active' => true,
            'priority' => fake()->numberBetween(1, 10),
        ];
    }
}
