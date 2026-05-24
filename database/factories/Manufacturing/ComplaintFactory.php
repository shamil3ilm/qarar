<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'uuid'                   => (string) Str::uuid(),
            'organization_id'        => Organization::factory(),
            'complaint_number'       => strtoupper(fake()->unique()->bothify('CMP-####-??')),
            'complaint_source'       => fake()->randomElement(['customer', 'internal', 'regulatory', 'supplier']),
            'contact_id'             => null,
            'subject'                => fake()->sentence(5),
            'description'            => fake()->paragraph(),
            'priority'               => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'status'                 => 'open',
            'assigned_to_id'         => null,
            'received_date'          => now()->format('Y-m-d'),
            'target_resolution_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'actual_resolution_date' => null,
        ];
    }
}
