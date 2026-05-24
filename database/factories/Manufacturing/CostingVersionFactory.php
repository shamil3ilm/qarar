<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Models\Manufacturing\CostingVersion;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostingVersionFactory extends Factory
{
    protected $model = CostingVersion::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'version_code'    => strtoupper(fake()->unique()->lexify('VER-????')),
            'description'     => fake()->sentence(4),
            'valid_from'      => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'valid_to'        => null,
            'status'          => CostingVersion::STATUS_DRAFT,
            'costing_type'    => CostingVersion::TYPE_STANDARD,
            'currency_code'   => 'SAR',
            'created_by'      => User::factory(),
        ];
    }
}
