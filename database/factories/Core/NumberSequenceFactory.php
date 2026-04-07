<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\NumberSequence;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class NumberSequenceFactory extends Factory
{
    protected $model = NumberSequence::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'type' => fake()->randomElement(['invoice', 'quotation', 'purchase_order', 'bill']),
            'prefix' => strtoupper(fake()->lexify('???')),
            'suffix' => null,
            'current_number' => fake()->numberBetween(1, 10000),
            'padding' => 6,
            'include_year' => true,
            'include_month' => false,
            'reset_yearly' => true,
            'reset_monthly' => false,
            'last_reset_year' => now()->year,
            'last_reset_month' => null,
        ];
    }
}
