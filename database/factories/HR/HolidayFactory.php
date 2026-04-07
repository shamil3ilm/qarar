<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Holiday;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => fake()->randomElement(['Eid al-Fitr', 'Eid al-Adha', 'National Day', 'New Year', 'Diwali']),
            'holiday_date' => fake()->dateTimeBetween('now', '+1 year'),
            'is_optional' => false,
            'is_restricted' => false,
            'applicable_to' => null,
            'description' => fake()->optional(0.3)->sentence(),
        ];
    }
}
