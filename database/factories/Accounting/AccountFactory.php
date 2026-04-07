<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
            Account::TYPE_INCOME,
            Account::TYPE_EXPENSE,
        ]);

        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'account_type' => $type,
            'sub_type' => null,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR', 'USD']),
            'is_active' => true,
            'is_system' => false,
            'is_header' => false,
            'level' => 1,
            'path' => null,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn () => ['account_type' => Account::TYPE_ASSET]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['account_type' => Account::TYPE_EXPENSE]);
    }

    public function income(): static
    {
        return $this->state(fn () => ['account_type' => Account::TYPE_INCOME]);
    }
}
