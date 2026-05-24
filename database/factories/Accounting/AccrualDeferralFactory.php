<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\AccrualDeferral;
use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccrualDeferralFactory extends Factory
{
    protected $model = AccrualDeferral::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 1200, 36000);
        $periods = fake()->randomElement([3, 6, 12]);

        return [
            'organization_id'   => Organization::factory(),
            'reference'         => 'AD-' . fake()->numerify('####'),
            'type'              => fake()->randomElement([
                AccrualDeferral::TYPE_ACCRUAL,
                AccrualDeferral::TYPE_DEFERRAL,
            ]),
            'debit_account_id'  => Account::factory(),
            'credit_account_id' => Account::factory(),
            'total_amount'      => $total,
            'per_period_amount' => round($total / $periods, 4),
            'currency_code'     => 'SAR',
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addMonths($periods)->toDateString(),
            'periods_total'     => $periods,
            'periods_posted'    => 0,
            'status'            => AccrualDeferral::STATUS_ACTIVE,
            'description'       => fake()->optional(0.5)->sentence(),
        ];
    }
}
