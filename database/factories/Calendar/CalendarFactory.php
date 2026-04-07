<?php

declare(strict_types=1);

namespace Database\Factories\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarFactory extends Factory
{
    protected $model = Calendar::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true) . ' Calendar',
            'color' => fake()->hexColor(),
            'description' => fake()->optional(0.5)->sentence(),
            'type' => Calendar::TYPE_PERSONAL,
            'is_default' => false,
            'is_visible' => true,
            'timezone' => 'Asia/Riyadh',
        ];
    }

    public function personal(): static
    {
        return $this->state(fn () => [
            'type' => Calendar::TYPE_PERSONAL,
        ]);
    }

    public function team(): static
    {
        return $this->state(fn () => [
            'type' => Calendar::TYPE_TEAM,
            'user_id' => null,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }
}
