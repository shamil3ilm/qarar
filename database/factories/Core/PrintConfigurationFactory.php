<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\PrintConfiguration;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrintConfigurationFactory extends Factory
{
    protected $model = PrintConfiguration::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'printer_type' => fake()->randomElement(['thermal', 'standard', 'label']),
            'default_paper_size' => fake()->randomElement(['A4', 'A5', 'letter', 'receipt']),
            'paper_sizes' => ['A4', 'A5'],
            'thermal_settings' => null,
            'margin_settings' => ['top' => 10, 'bottom' => 10, 'left' => 15, 'right' => 15],
            'font_settings' => ['family' => 'Arial', 'size' => 12],
            'auto_cut' => false,
            'open_drawer' => false,
            'copies' => 1,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
