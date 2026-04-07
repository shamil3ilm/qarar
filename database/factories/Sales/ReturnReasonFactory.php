<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ReturnReason;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReturnReasonFactory extends Factory
{
    protected $model = ReturnReason::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Defective', 'Wrong Item', 'Not as Described', 'Changed Mind', 'Damaged in Transit']),
            'code' => strtoupper(fake()->unique()->lexify('RR-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'requires_evidence' => fake()->boolean(30),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 10),
        ];
    }
}
