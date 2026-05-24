<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialCloseTemplate;
use App\Models\Accounting\FinancialCloseTemplateTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCloseTemplateTask>
 */
class FinancialCloseTemplateTaskFactory extends Factory
{
    protected $model = FinancialCloseTemplateTask::class;

    public function definition(): array
    {
        return [
            'financial_close_template_id' => FinancialCloseTemplate::factory(),
            'task_name'                   => $this->faker->words(4, true),
            'task_type'                   => 'manual',
            'sort_order'                  => $this->faker->numberBetween(1, 100),
        ];
    }
}
