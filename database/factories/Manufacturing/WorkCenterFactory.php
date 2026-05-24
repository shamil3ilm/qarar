<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkCenterFactory extends Factory
{
    protected $model = WorkCenter::class;

    public function definition(): array
    {
        return [
            'organization_id'    => Organization::factory(),
            'code'               => strtoupper(fake()->unique()->bothify('WC-###')),
            'name'               => fake()->words(2, true) . ' Center',
            'description'        => null,
            'work_center_type'   => fake()->randomElement(['machine', 'labor', 'assembly', 'inspection', 'other']),
            'capacity_per_day'   => 8,
            'efficiency_percent' => 100,
            'calendar_type'      => '5day',
            'cost_per_hour'      => 50,
            'currency_code'      => 'USD',
            'is_active'          => true,
            'created_by'         => null,
        ];
    }
}
