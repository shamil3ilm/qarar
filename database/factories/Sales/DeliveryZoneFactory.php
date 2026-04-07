<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\DeliveryZone;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city() . ' Zone',
            'code' => strtoupper(fake()->unique()->lexify('DZ-???')),
            'countries' => [fake()->countryCode()],
            'states' => null,
            'cities' => null,
            'postal_codes' => null,
            'is_active' => true,
        ];
    }
}
