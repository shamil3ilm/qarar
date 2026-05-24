<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\FixedAsset;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1000, 100000);

        return [
            'organization_id'          => Organization::factory(),
            'asset_category_id'        => AssetCategory::factory(),
            'asset_number'             => 'FA-' . fake()->unique()->numerify('####'),
            'name'                     => fake()->words(3, true),
            'status'                   => FixedAsset::STATUS_ACTIVE,
            'acquisition_date'         => now()->subYears(1)->toDateString(),
            'acquisition_cost'         => $cost,
            'salvage_value'            => 0,
            'useful_life_years'        => 5,
            'depreciation_method'      => FixedAsset::DEPRECIATION_STRAIGHT_LINE,
            'accumulated_depreciation' => 0,
            'book_value'               => $cost,
        ];
    }
}
