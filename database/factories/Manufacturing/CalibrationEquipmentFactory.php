<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\CalibrationEquipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CalibrationEquipmentFactory extends Factory
{
    protected $model = CalibrationEquipment::class;

    public function definition(): array
    {
        return [
            'uuid'                  => (string) Str::uuid(),
            'organization_id'       => Organization::factory(),
            'equipment_code'        => strtoupper(fake()->unique()->bothify('CAL-###')),
            'name'                  => fake()->words(2, true) . ' Meter',
            'manufacturer'          => null,
            'model_number'          => null,
            'serial_number'         => null,
            'category'              => CalibrationEquipment::CATEGORY_SCALE,
            'location'              => null,
            'responsible_person_id' => null,
            'purchase_date'         => null,
            'is_active'             => true,
        ];
    }
}
