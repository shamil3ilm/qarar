<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\RoutingHeader;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoutingHeaderFactory extends Factory
{
    protected $model = RoutingHeader::class;

    public function definition(): array
    {
        return [
            'uuid'            => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'product_id'      => Product::factory(),
            'routing_number'  => strtoupper(fake()->unique()->bothify('RTG-####')),
            'alternative'     => '01',
            'is_default'      => true,
            'valid_from'      => now()->format('Y-m-d'),
            'valid_to'        => null,
        ];
    }
}
