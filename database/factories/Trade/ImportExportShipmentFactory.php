<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Trade\ImportExportShipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportExportShipmentFactory extends Factory
{
    protected $model = ImportExportShipment::class;

    public function definition(): array
    {
        $fob = fake()->randomFloat(4, 5000, 200000);
        $freight = fake()->randomFloat(4, 500, 10000);
        $insurance = fake()->randomFloat(4, 100, 5000);
        $cif = round($fob + $freight + $insurance, 4);

        return [
            'organization_id' => Organization::factory(),
            'shipment_number' => fake()->unique()->numerify('SHP-IMP-2025-######'),
            'shipment_type' => fake()->randomElement([
                ImportExportShipment::TYPE_IMPORT,
                ImportExportShipment::TYPE_EXPORT,
            ]),
            'contact_id' => Contact::factory(),
            'transport_mode' => fake()->randomElement([
                ImportExportShipment::TRANSPORT_SEA,
                ImportExportShipment::TRANSPORT_AIR,
                ImportExportShipment::TRANSPORT_ROAD,
            ]),
            'currency_code' => fake()->randomElement(['USD', 'EUR', 'SAR', 'AED']),
            'exchange_rate' => 1.00000000,
            'fob_value' => $fob,
            'freight_value' => $freight,
            'insurance_value' => $insurance,
            'cif_value' => $cif,
            'other_charges' => 0,
            'status' => ImportExportShipment::STATUS_PENDING,
            'created_by' => User::factory(),
        ];
    }

    public function import(): static
    {
        return $this->state(fn () => [
            'shipment_type' => ImportExportShipment::TYPE_IMPORT,
        ]);
    }

    public function export(): static
    {
        return $this->state(fn () => [
            'shipment_type' => ImportExportShipment::TYPE_EXPORT,
        ]);
    }
}
