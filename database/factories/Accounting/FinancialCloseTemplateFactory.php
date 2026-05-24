<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialCloseTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCloseTemplate>
 */
class FinancialCloseTemplateFactory extends Factory
{
    protected $model = FinancialCloseTemplate::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->words(3, true),
            'close_type' => $this->faker->randomElement(['month_end', 'quarter_end', 'year_end']),
            'is_active'  => true,
        ];
    }
}
