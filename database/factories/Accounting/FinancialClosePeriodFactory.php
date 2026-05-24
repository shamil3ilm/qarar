<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialClosePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialClosePeriod>
 */
class FinancialClosePeriodFactory extends Factory
{
    protected $model = FinancialClosePeriod::class;

    public function definition(): array
    {
        return [
            'fiscal_year' => 2025,
            'period'      => $this->faker->numberBetween(1, 12),
            'close_type'  => 'month_end',
            'status'      => FinancialClosePeriod::STATUS_OPEN,
        ];
    }
}
