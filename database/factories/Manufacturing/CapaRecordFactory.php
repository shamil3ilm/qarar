<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\CapaRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CapaRecordFactory extends Factory
{
    protected $model = CapaRecord::class;

    public function definition(): array
    {
        return [
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => Organization::factory(),
            'capa_number'       => strtoupper(fake()->unique()->bothify('CAPA-####-??')),
            'capa_type'         => fake()->randomElement(['corrective', 'preventive']),
            'problem_statement' => fake()->sentence(),
            'root_cause'        => fake()->sentence(),
            'priority'          => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'status'            => 'open',
            'owner_id'          => null,
            'target_close_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'actual_close_date' => null,
            'source_type'       => null,
            'source_id'         => null,
        ];
    }
}
