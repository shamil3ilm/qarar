<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\ImportTemplate;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportTemplateFactory extends Factory
{
    protected $model = ImportTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Template',
            'entity_type' => fake()->randomElement(['products', 'contacts', 'invoices']),
            'column_mapping' => ['col_a' => 'name', 'col_b' => 'email'],
            'options' => null,
            'is_default' => false,
        ];
    }
}
