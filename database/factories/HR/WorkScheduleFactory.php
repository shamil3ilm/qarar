<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\WorkSchedule;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Standard', 'Flexible', 'Night Shift', 'Morning Shift']),
            'code' => strtoupper(fake()->unique()->lexify('WS-???')),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration' => 1.0,
            'working_hours' => 8.0,
            'work_days' => [1, 2, 3, 4, 5],
            'is_flexible' => false,
            'grace_period_minutes' => 15,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
