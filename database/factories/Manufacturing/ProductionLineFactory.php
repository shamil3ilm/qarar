<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductionLineFactory extends Factory
{
    protected $model = ProductionLine::class;

    public function definition(): array
    {
        return [
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => Organization::factory(),
            'code'              => strtoupper(fake()->unique()->bothify('PL-###')),
            'name'              => fake()->words(2, true) . ' Line',
            'work_center_id'    => null,
            'capacity_per_hour' => 100,
            'unit_id'           => null,
            'is_active'         => true,
        ];
    }
}
