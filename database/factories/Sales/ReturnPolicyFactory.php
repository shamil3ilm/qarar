<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\ReturnPolicy;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReturnPolicyFactory extends Factory
{
    protected $model = ReturnPolicy::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Policy',
            'description' => fake()->optional(0.5)->sentence(),
            'return_window_days' => fake()->randomElement([7, 14, 30, 60, 90]),
            'allow_exchange' => true,
            'allow_refund' => true,
            'allow_credit_note' => true,
            'require_receipt' => true,
            'require_original_packaging' => false,
            'require_approval' => fake()->boolean(50),
            'restocking_fee_percent' => fake()->randomFloat(2, 0, 15),
            'non_returnable_categories' => null,
            'condition_requirements' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
