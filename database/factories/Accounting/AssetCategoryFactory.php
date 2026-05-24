<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return [
            'organization_id'              => Organization::factory(),
            'name'                         => fake()->words(2, true) . ' Assets',
            'code'                         => strtoupper(fake()->unique()->lexify('??##')),
            'description'                  => fake()->sentence(),
            'default_useful_life_years'    => fake()->randomElement([3, 5, 10, 20]),
            'default_depreciation_method'  => AssetCategory::DEPRECIATION_STRAIGHT_LINE,
            'default_salvage_percent'      => 0,
            'is_active'                    => true,
        ];
    }
}
