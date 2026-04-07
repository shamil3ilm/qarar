<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\ExciseDeclaration;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExciseDeclarationFactory extends Factory
{
    protected $model = ExciseDeclaration::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'declaration_number' => 'EXD-' . fake()->unique()->numerify('######'),
            'declaration_type' => fake()->randomElement(['production', 'import', 'stock_transfer']),
            'period_from' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'period_to' => fake()->dateTimeBetween('-1 month', 'now'),
            'total_excisable_value' => fake()->randomFloat(4, 1000, 500000),
            'total_excise_duty' => fake()->randomFloat(4, 100, 50000),
            'total_deductions' => 0,
            'net_payable' => fake()->randomFloat(4, 100, 50000),
            'status' => fake()->randomElement(['draft', 'submitted', 'paid', 'rejected']),
            'submitted_at' => null,
            'paid_at' => null,
            'payment_reference' => null,
            'notes' => null,
            'journal_entry_id' => null,
            'created_by' => null,
        ];
    }
}
