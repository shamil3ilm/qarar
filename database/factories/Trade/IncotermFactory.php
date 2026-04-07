<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\Incoterm;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncotermFactory extends Factory
{
    protected $model = Incoterm::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF']),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'version' => '2020',
            'seller_responsibilities' => fake()->optional(0.3)->sentence(),
            'buyer_responsibilities' => fake()->optional(0.3)->sentence(),
            'risk_transfer_point' => fake()->optional(0.3)->sentence(),
            'cost_transfer_point' => fake()->optional(0.3)->sentence(),
            'transport_modes' => null,
            'is_active' => true,
        ];
    }
}
