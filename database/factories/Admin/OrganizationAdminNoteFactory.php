<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\OrganizationAdminNote;
use App\Models\Core\Organization;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationAdminNoteFactory extends Factory
{
    protected $model = OrganizationAdminNote::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'admin_id' => PlatformAdmin::factory(),
            'note' => fake()->paragraph(),
            'note_type' => fake()->randomElement(['general', 'support', 'billing', 'warning']),
            'is_internal' => true,
            'is_pinned' => false,
        ];
    }
}