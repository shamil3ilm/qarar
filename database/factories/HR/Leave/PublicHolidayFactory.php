<?php

declare(strict_types=1);

namespace Database\Factories\HR\Leave;

use App\Models\HR\Leave\PublicHoliday;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublicHolidayFactory extends Factory
{
    protected $model = PublicHoliday::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'name' => fake()->randomElement(['Eid al-Fitr', 'Eid al-Adha', 'National Day', 'New Year']),
            'holiday_date' => fake()->dateTimeBetween('now', '+1 year'),
            'country_code' => fake()->randomElement(['SA', 'AE', 'IN']),
            'state_code' => null,
            'is_recurring' => false,
            'is_optional' => false,
            'year' => now()->year,
        ];
    }
}
