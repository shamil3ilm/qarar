<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\LtpSimulation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LtpSimulationFactory extends Factory
{
    protected $model = LtpSimulation::class;

    public function definition(): array
    {
        return [
            'uuid'                  => (string) Str::uuid(),
            'organization_id'       => Organization::factory(),
            'name'                  => fake()->words(3, true),
            'description'           => null,
            'planning_horizon_from' => now()->format('Y-m-d'),
            'planning_horizon_to'   => now()->addMonths(3)->format('Y-m-d'),
            'status'                => 'draft',
            'mrp_run_id'            => null,
            'created_by'            => null,
            'run_at'                => null,
        ];
    }
}
