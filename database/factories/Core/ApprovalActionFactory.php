<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ApprovalAction;
use App\Models\Core\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'workflow_step_id' => null,
            'assigned_to' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'delegated_to' => null,
            'delegated_at' => null,
            'comments' => fake()->optional(0.5)->sentence(),
            'action_at' => fake()->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'action_by' => null,
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 day', '+1 month'),
            'reminder_sent' => false,
        ];
    }
}
