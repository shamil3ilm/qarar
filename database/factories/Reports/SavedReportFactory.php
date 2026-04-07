<?php

declare(strict_types=1);

namespace Database\Factories\Reports;

use App\Models\Reports\SavedReport;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavedReportFactory extends Factory
{
    protected $model = SavedReport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Report',
            'report_type' => fake()->randomElement(['profit_loss', 'balance_sheet', 'sales_summary', 'inventory_valuation']),
            'parameters' => ['start_date' => now()->subMonth()->toDateString()],
            'columns' => null,
            'schedule_frequency' => fake()->optional(0.3)->randomElement(['daily', 'weekly', 'monthly']),
            'schedule_day' => null,
            'schedule_time' => null,
            'recipients' => null,
            'export_format' => fake()->randomElement(['pdf', 'xlsx', 'csv']),
            'is_public' => false,
            'last_run_at' => null,
            'next_run_at' => null,
            'is_active' => true,
        ];
    }
}
