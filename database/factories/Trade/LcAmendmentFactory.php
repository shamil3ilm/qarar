<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Trade\LcAmendment;
use App\Models\Trade\LetterOfCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

class LcAmendmentFactory extends Factory
{
    protected $model = LcAmendment::class;

    public function definition(): array
    {
        return [
            'lc_id' => LetterOfCredit::factory(),
            'amendment_number' => fake()->numberBetween(1, 10),
            'amendment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->sentence(),
            'old_values' => ['amount' => 100000],
            'new_values' => ['amount' => 120000],
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'rejected']),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
