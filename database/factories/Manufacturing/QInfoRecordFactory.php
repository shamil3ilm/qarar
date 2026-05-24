<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\QInfoRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class QInfoRecordFactory extends Factory
{
    protected $model = QInfoRecord::class;

    public function definition(): array
    {
        return [
            'organization_id'          => Organization::factory(),
            'vendor_id'                => null,
            'product_id'               => Product::factory(),
            'inspection_type'          => fake()->randomElement(['goods_receipt', 'in_process', 'final', 'delivery', 'returns']),
            'skip_lot_plan_id'         => null,
            'quality_plan_id'          => null,
            'is_active'                => true,
            'release_required'         => false,
            'cert_required'            => false,
            'cert_type'                => null,
            'inspection_interval_days' => null,
            'last_inspection_date'     => null,
            'next_inspection_date'     => null,
            'notes'                    => null,
        ];
    }
}
