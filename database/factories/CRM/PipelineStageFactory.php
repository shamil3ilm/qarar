<?php

declare(strict_types=1);

namespace Database\Factories\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        $stages = [
            ['name' => 'Prospecting', 'probability' => 10],
            ['name' => 'Qualification', 'probability' => 20],
            ['name' => 'Proposal', 'probability' => 40],
            ['name' => 'Negotiation', 'probability' => 60],
            ['name' => 'Closing', 'probability' => 80],
        ];

        $stage = fake()->randomElement($stages);

        return [
            'organization_id' => Organization::factory(),
            'name' => $stage['name'],
            'code' => strtoupper(fake()->unique()->lexify('STG-???')),
            'description' => fake()->optional(0.5)->sentence(),
            'probability' => $stage['probability'],
            'sort_order' => fake()->numberBetween(1, 10),
            'color' => fake()->hexColor(),
            'is_won' => false,
            'is_lost' => false,
            'is_active' => true,
        ];
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'name' => 'Won',
            'probability' => 100,
            'is_won' => true,
            'is_lost' => false,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'name' => 'Lost',
            'probability' => 0,
            'is_won' => false,
            'is_lost' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function atOrder(int $order): static
    {
        return $this->state(fn () => ['sort_order' => $order]);
    }
}
