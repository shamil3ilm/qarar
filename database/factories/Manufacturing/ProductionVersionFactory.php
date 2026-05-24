<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\ProductionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionVersionFactory extends Factory
{
    protected $model = ProductionVersion::class;

    public function definition(): array
    {
        return [
            'organization_id'  => Organization::factory(),
            'product_id'       => Product::factory(),
            'version_code'     => strtoupper(fake()->unique()->bothify('V##')),
            'description'      => null,
            'bom_id'           => null,
            'routing_id'       => null,
            'lot_size_from'    => 1,
            'lot_size_to'      => null,
            'valid_from'       => now()->format('Y-m-d'),
            'valid_to'         => null,
            'production_plant' => null,
            'is_default'       => false,
            'is_active'        => true,
        ];
    }
}
