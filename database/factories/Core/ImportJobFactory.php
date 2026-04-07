<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ImportJob;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'entity_type' => fake()->randomElement(['products', 'contacts', 'chart_of_accounts']),
            'file_name' => fake()->slug() . '.csv',
            'file_path' => 'imports/' . fake()->slug() . '.csv',
            'original_name' => fake()->words(3, true) . '.csv',
            'file_size' => fake()->numberBetween(1024, 5242880),
            'status' => fake()->randomElement(['pending', 'validating', 'processing', 'completed', 'failed']),
            'total_rows' => fake()->numberBetween(10, 5000),
            'processed_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'column_mapping' => null,
            'options' => null,
            'errors' => null,
            'summary' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
