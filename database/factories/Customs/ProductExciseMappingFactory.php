<?php

declare(strict_types=1);

namespace Database\Factories\Customs;

use App\Models\Customs\ProductExciseMapping;
use App\Models\Customs\ExciseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductExciseMappingFactory extends Factory
{
    protected $model = ProductExciseMapping::class;

    public function definition(): array
    {
        return [
            'product_id' => null,
            'excise_category_id' => ExciseCategory::factory(),
            'excise_rate_id' => null,
            'is_active' => true,
        ];
    }
}
