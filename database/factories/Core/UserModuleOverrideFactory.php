<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\UserModuleOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserModuleOverrideFactory extends Factory
{
    protected $model = UserModuleOverride::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_id' => null,
            'override_type' => fake()->randomElement(['grant', 'revoke']),
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => false,
            'can_export' => true,
            'can_import' => false,
            'can_approve' => false,
            'data_scope' => fake()->randomElement(['own', 'branch', 'all']),
            'max_amount_limit' => null,
            'custom_permissions' => null,
            'reason' => fake()->optional(0.5)->sentence(),
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 month', '+1 year'),
            'granted_by' => null,
        ];
    }
}
