<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Manufacturing\QualityPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QualityPlanFactory extends Factory
{
    protected $model = QualityPlan::class;

    public function definition(): array
    {
        return [
            'uuid'                => (string) Str::uuid(),
            'organization_id'     => Organization::factory(),
            'name'                => fake()->words(3, true),
            'product_id'          => null,
            'product_category_id' => null,
            'inspection_stage'    => fake()->randomElement(['goods_receipt', 'production', 'pre_shipment', 'in_process', 'final']),
            'is_active'           => true,
            'description'         => null,
            'created_by'          => null,
        ];
    }
}
