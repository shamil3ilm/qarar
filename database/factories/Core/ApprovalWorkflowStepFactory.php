<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ApprovalWorkflowStep;
use App\Models\Core\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalWorkflowStepFactory extends Factory
{
    protected $model = ApprovalWorkflowStep::class;

    public function definition(): array
    {
        return [
            'approval_workflow_id' => ApprovalWorkflow::factory(),
            'workflow_id' => null,
            'name' => fake()->words(2, true) . ' Step',
            'sequence' => fake()->numberBetween(1, 5),
            'step_order' => fake()->numberBetween(1, 5),
            'approver_type' => fake()->randomElement(['user', 'role', 'manager']),
            'approver_id' => null,
            'approver_user_id' => null,
            'approver_role_id' => null,
            'approver_custom' => null,
            'approval_type' => fake()->randomElement(['any', 'all']),
            'action_type' => fake()->randomElement(['approve', 'review']),
            'requires_all' => false,
            'requires_comment' => false,
            'min_approvers' => 1,
            'timeout_hours' => fake()->optional(0.5)->numberBetween(4, 72),
            'escalate_to_user_id' => null,
            'can_reject' => true,
            'can_skip' => false,
            'can_delegate' => false,
        ];
    }
}
