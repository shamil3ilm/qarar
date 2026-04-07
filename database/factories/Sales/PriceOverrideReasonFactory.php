<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\PriceOverrideReason;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceOverrideReasonFactory extends Factory
{
    protected $model = PriceOverrideReason::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Customer Loyalty', 'Bulk Order', 'Damaged Item', 'Competitor Match', 'Negotiation']),
            'code' => strtoupper(fake()->unique()->lexify('POR-???')),
            'description' => fake()->optional(0.3)->sentence(),
            'requires_approval' => fake()->boolean(50),
            'requires_evidence' => false,
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 10),
        ];
    }
}
