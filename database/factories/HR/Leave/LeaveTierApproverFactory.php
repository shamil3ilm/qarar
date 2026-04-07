<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\LeaveTierApprover;
use App\Models\HR\Leave\LeaveTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTierApproverFactory extends Factory
{
    protected $model = LeaveTierApprover::class;

    public function definition(): array
    {
        return [
            'leave_tier_id' => LeaveTier::factory(),
            'user_id' => null,
            'role_id' => null,
            'designation' => fake()->optional(0.3)->jobTitle(),
            'approval_level' => fake()->numberBetween(1, 3),
            'can_approve' => true,
            'can_reject' => true,
            'is_final_approver' => false,
        ];
    }
}
