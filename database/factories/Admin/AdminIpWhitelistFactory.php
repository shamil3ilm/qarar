<?php

declare(strict_types=1);

namespace Database\Factories\Admin;

use App\Models\Admin\AdminIpWhitelist;
use App\Models\Admin\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminIpWhitelistFactory extends Factory
{
    protected $model = AdminIpWhitelist::class;

    public function definition(): array
    {
        return [
            'admin_id' => PlatformAdmin::factory(),
            'ip_address' => fake()->ipv4(),
            'description' => fake()->optional(0.5)->sentence(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}