<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ApprovalRequest;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'approval_workflow_id' => null,
            'approvable_type' => fake()->randomElement(['App\Models\Sales\Invoice', 'App\Models\Purchase\PurchaseOrder']),
            'approvable_id' => fake()->numberBetween(1, 1000),
            'current_step_id' => null,
            'status' => fake()->randomElement(['pending', 'in_progress', 'approved', 'rejected']),
            'amount' => fake()->randomFloat(4, 100, 100000),
            'notes' => fake()->optional(0.3)->sentence(),
            'submitted_at' => now(),
            'completed_at' => null,
            'submitted_by' => null,
        ];
    }
}
