<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'code'   => strtoupper($this->faker->unique()->bothify('CC-###')),
            'name'   => $this->faker->words(3, true),
            'status' => CostCenter::STATUS_ACTIVE,
        ];
    }
}
