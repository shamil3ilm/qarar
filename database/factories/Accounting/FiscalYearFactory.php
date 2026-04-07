<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'FY ' . fake()->year(),
            'start_date' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'end_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_current' => false,
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ];
    }
}