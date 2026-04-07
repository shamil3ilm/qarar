<?php

declare(strict_types=1);

namespace Database\Factories\Trade;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Trade\LetterOfCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LetterOfCreditFactory extends Factory
{
    protected $model = LetterOfCredit::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(4, 10000, 500000);

        return [
            'organization_id' => Organization::factory(),
            'lc_number' => fake()->unique()->numerify('LC-######'),
            'lc_type' => LetterOfCredit::TYPE_IMPORT,
            'is_irrevocable' => true,
            'is_confirmed' => false,
            'issuing_bank' => fake()->company() . ' Bank',
            'issuing_bank_swift' => strtoupper(fake()->lexify('????????') . fake()->numerify('###')),
            'advising_bank' => fake()->optional(0.5)->company(),
            'applicant_id' => Contact::factory(),
            'beneficiary_id' => Contact::factory(),
            'currency_code' => fake()->randomElement(['USD', 'EUR', 'GBP', 'SAR', 'AED']),
            'amount' => $amount,
            'tolerance_percent' => fake()->randomElement([0, 5, 10]),
            'utilized_amount' => 0,
            'available_amount' => $amount,
            'issue_date' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
            'expiry_date' => now()->addDays(fake()->numberBetween(30, 180))->toDateString(),
            'presentation_days' => 21,
            'partial_shipments_allowed' => false,
            'transhipment_allowed' => false,
            'status' => LetterOfCredit::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => LetterOfCredit::STATUS_ISSUED,
        ]);
    }

    public function export(): static
    {
        return $this->state(fn () => [
            'lc_type' => LetterOfCredit::TYPE_EXPORT,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expiry_date' => now()->subDays(10)->toDateString(),
            'status' => LetterOfCredit::STATUS_EXPIRED,
        ]);
    }
}
