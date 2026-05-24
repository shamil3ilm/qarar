<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialCloseTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialCloseTask>
 */
class FinancialCloseTaskFactory extends Factory
{
    protected $model = FinancialCloseTask::class;

    public function definition(): array
    {
        return [
            'task_name'  => $this->faker->words(4, true),
            'task_type'  => 'manual',
            'status'     => FinancialCloseTask::STATUS_PENDING,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
