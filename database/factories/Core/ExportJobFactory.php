<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ExportJob;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'entity_type' => fake()->randomElement(['invoices', 'products', 'contacts']),
            'format' => fake()->randomElement(['csv', 'xlsx', 'pdf']),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'filters' => null,
            'columns' => null,
            'options' => null,
            'total_records' => fake()->numberBetween(10, 10000),
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
        ];
    }
}
