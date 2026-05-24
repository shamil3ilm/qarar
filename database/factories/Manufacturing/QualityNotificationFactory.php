<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\QualityNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QualityNotificationFactory extends Factory
{
    protected $model = QualityNotification::class;

    public function definition(): array
    {
        return [
            'uuid'                => (string) Str::uuid(),
            'organization_id'     => Organization::factory(),
            'notification_number' => strtoupper(fake()->unique()->bothify('QN-####-??')),
            'notification_type'   => fake()->randomElement(['defect', 'complaint', 'improvement', 'deviation']),
            'source_type'         => 'internal',
            'source_id'           => null,
            'product_id'          => null,
            'title'               => fake()->sentence(5),
            'description'         => fake()->paragraph(),
            'priority'            => 'medium',
            'status'              => 'open',
            'assigned_to'         => null,
            'root_cause'          => null,
            'corrective_action'   => null,
            'preventive_action'   => null,
            'due_date'            => null,
            'resolved_at'         => null,
            'resolved_by'         => null,
            'created_by'          => null,
        ];
    }
}
