<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\AuditPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditPlanFactory extends Factory
{
    protected $model = AuditPlan::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+30 days');
        $end   = (clone $start)->modify('+' . fake()->numberBetween(1, 5) . ' days');

        return [
            'uuid'            => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'plan_number'     => strtoupper(fake()->unique()->bothify('AUD-####-??')),
            'title'           => fake()->sentence(4),
            'audit_type'      => fake()->randomElement(['internal', 'supplier', 'customer', 'regulatory', 'certification']),
            'planned_start'   => $start->format('Y-m-d'),
            'planned_end'     => $end->format('Y-m-d'),
            'lead_auditor_id' => null,
            'status'          => 'draft',
            'scope'           => null,
            'objectives'      => null,
        ];
    }
}
