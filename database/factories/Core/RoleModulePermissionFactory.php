<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\RoleModulePermission;
use App\Models\Core\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleModulePermissionFactory extends Factory
{
    protected $model = RoleModulePermission::class;

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'module_id' => null,
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => false,
            'can_export' => true,
            'can_import' => false,
            'can_approve' => false,
            'can_print' => true,
            'data_scope' => fake()->randomElement(['own', 'branch', 'all']),
            'max_amount_limit' => null,
            'max_discount_percent' => null,
            'custom_permissions' => null,
        ];
    }
}
