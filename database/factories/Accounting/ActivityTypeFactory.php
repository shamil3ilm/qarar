<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityType>
 */
class ActivityTypeFactory extends Factory
{
    protected $model = ActivityType::class;

    public function definition(): array
    {
        return [
            'code'            => strtoupper($this->faker->unique()->bothify('AT-###')),
            'name'            => $this->faker->words(3, true),
            'unit_of_measure' => $this->faker->randomElement(['hours', 'units', 'kg', 'pieces']),
            'is_active'       => true,
        ];
    }
}
