<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');
        $endDate = (clone $startDate)->modify('+1 month -1 day');

        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'period_number' => fake()->numberBetween(1, 12),
            'period_type' => 'monthly',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'closed_at' => now(),
        ]);
    }
}
