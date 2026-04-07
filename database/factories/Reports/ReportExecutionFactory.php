<?php

declare(strict_types=1);

namespace Database\Factories\Reports;

use App\Models\Reports\ReportExecution;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportExecutionFactory extends Factory
{
    protected $model = ReportExecution::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'saved_report_id' => null,
            'user_id' => null,
            'report_type' => fake()->randomElement(['profit_loss', 'balance_sheet', 'sales_summary', 'inventory_valuation']),
            'parameters' => ['start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->toDateString()],
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'file_path' => null,
            'file_format' => fake()->randomElement(['pdf', 'xlsx', 'csv']),
            'file_size' => fake()->optional(0.5)->numberBetween(1024, 5242880),
            'row_count' => fake()->optional(0.5)->numberBetween(10, 10000),
            'execution_time_ms' => fake()->optional(0.5)->numberBetween(100, 30000),
            'error_message' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => fake()->optional(0.5)->dateTimeBetween('-5 minutes', 'now'),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 day', '+30 days'),
        ];
    }
}
