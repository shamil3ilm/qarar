<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\DocumentSplittingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSplittingRule>
 */
class DocumentSplittingRuleFactory extends Factory
{
    protected $model = DocumentSplittingRule::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->words(3, true),
            'split_method'       => $this->faker->randomElement(['segment', 'profit_center', 'cost_center']),
            'base_item_category' => $this->faker->randomElement(['revenue', 'expense', 'asset']),
            'is_active'          => true,
            'priority'           => $this->faker->numberBetween(1, 100),
        ];
    }
}
