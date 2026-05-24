<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\ScrapReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScrapReportFactory extends Factory
{
    protected $model = ScrapReport::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'work_order_id'   => null,
            'product_id'      => Product::factory(),
            'warehouse_id'    => null,
            'scrap_date'      => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'scrap_quantity'  => fake()->randomFloat(4, 1, 100),
            'unit_of_measure' => 'EA',
            'scrap_cause'     => fake()->randomElement(['defect', 'damage', 'obsolete', 'process_loss', 'machine_failure', 'other']),
            'scrap_code'      => null,
            'description'     => fake()->sentence(),
            'estimated_value' => fake()->randomFloat(4, 10, 10000),
            'is_recoverable'  => false,
            'recovery_value'  => 0,
            'gl_posted'       => false,
            'gl_posted_at'    => null,
            'reported_by'     => User::factory(),
        ];
    }
}
