<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\ProductionResourceTool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductionResourceToolFactory extends Factory
{
    protected $model = ProductionResourceTool::class;

    public function definition(): array
    {
        return [
            'uuid'                  => (string) Str::uuid(),
            'organization_id'       => Organization::factory(),
            'prt_number'            => strtoupper(fake()->unique()->bothify('PRT-####-???')),
            'prt_name'              => fake()->words(3, true),
            'prt_type'              => fake()->randomElement(['tool', 'fixture', 'jig', 'test_equipment', 'document', 'program']),
            'status'                => 'available',
            'location'              => null,
            'quantity_available'    => 1,
            'quantity_in_use'       => 0,
            'serial_number'         => null,
            'next_calibration_date' => null,
            'notes'                 => null,
        ];
    }
}
