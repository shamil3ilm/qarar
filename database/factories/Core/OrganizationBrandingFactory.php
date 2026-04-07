<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\OrganizationBranding;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationBrandingFactory extends Factory
{
    protected $model = OrganizationBranding::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'logo_url' => null,
            'logo_dark_url' => null,
            'favicon_url' => null,
            'login_background_url' => null,
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'accent_color' => fake()->hexColor(),
            'danger_color' => '#dc3545',
            'warning_color' => '#ffc107',
            'success_color' => '#28a745',
            'info_color' => '#17a2b8',
            'text_color' => '#333333',
            'background_color' => '#ffffff',
            'sidebar_color' => null,
            'header_color' => null,
            'font_family' => fake()->randomElement(['Inter', 'Roboto', 'Open Sans']),
        ];
    }
}
