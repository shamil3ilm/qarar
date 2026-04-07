<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\CustomsDeclaration;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomsDeclarationFactory extends Factory
{
    protected $model = CustomsDeclaration::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'branch_id' => null,
            'declaration_number' => 'CD-' . fake()->unique()->numerify('######'),
            'declaration_type' => fake()->randomElement(['import', 'export', 'transit']),
            'customs_regime' => fake()->randomElement(['home_use', 'warehousing', 'temporary', 'transit']),
            'source_type' => null,
            'source_id' => null,
            'importer_exporter_id' => null,
            'broker_id' => null,
            'consignee_name' => fake()->company(),
            'consignor_name' => fake()->company(),
            'customs_office' => fake()->city() . ' Customs',
            'port_of_entry' => fake()->city(),
            'port_of_exit' => fake()->city(),
            'country_of_origin' => fake()->countryCode(),
            'country_of_destination' => fake()->countryCode(),
            'country_of_consignment' => fake()->countryCode(),
            'incoterm' => fake()->randomElement(['FOB', 'CIF', 'EXW', 'DDP']),
            'transport_mode' => fake()->randomElement(['sea', 'air', 'road', 'rail']),
            'vessel_name' => fake()->optional(0.5)->company(),
        ];
    }
}
