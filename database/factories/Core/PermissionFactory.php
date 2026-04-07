<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $modules = ['sales', 'accounting', 'inventory', 'purchase', 'hr', 'crm', 'manufacturing'];
        $actions = ['view', 'create', 'update', 'delete'];
        $module = fake()->randomElement($modules);
        $action = fake()->randomElement($actions);

        return [
            'name' => ucfirst($action) . ' ' . ucfirst($module),
            'slug' => $module . '.' . fake()->unique()->word() . '.' . $action,
            'module' => $module,
            'description' => "Can {$action} {$module} records",
        ];
    }

    public function forModule(string $module): static
    {
        return $this->state(fn () => ['module' => $module]);
    }
}
