<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\FinancialStatementVersion;
use App\Models\Accounting\FinancialStatementVersionNode;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialStatementVersionNodeFactory extends Factory
{
    protected $model = FinancialStatementVersionNode::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'fsv_id' => FinancialStatementVersion::factory(),
            'parent_id' => null,
            'account_id' => null,
            'node_type' => fake()->randomElement(['header', 'total']),
            'label' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 99),
            'sign' => 1,
        ];
    }
}
