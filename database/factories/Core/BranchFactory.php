<?php

declare(strict_types=1);

namespace Database\Factories\Core;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city() . ' Branch',
            'code' => strtoupper(fake()->lexify('BR-???')),
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country_code' => 'SA',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
