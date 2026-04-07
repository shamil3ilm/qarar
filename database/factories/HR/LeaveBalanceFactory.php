<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\Leave\LeaveBalance;
use App\Models\HR\Leave\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        $entitledDays = fake()->randomFloat(2, 10, 30);
        $usedDays = fake()->randomFloat(2, 0, $entitledDays * 0.6);
        $pendingDays = fake()->randomFloat(2, 0, 3);
        $carriedForward = fake()->randomFloat(2, 0, 5);
        $availableBalance = round($entitledDays + $carriedForward - $usedDays - $pendingDays, 2);

        return [
            'organization_id' => null,
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'leave_tier_id' => null,
            'year' => now()->year,
            'opening_balance' => 0,
            'entitled_days' => $entitledDays,
            'accrued_days' => 0,
            'adjustment_days' => 0,
            'used_days' => $usedDays,
            'pending_days' => $pendingDays,
            'carried_forward' => $carriedForward,
            'encashed_days' => 0,
            'lapsed_days' => 0,
            'available_balance' => max(0, $availableBalance),
            'last_accrual_date' => null,
        ];
    }

    public function forYear(int $year): static
    {
        return $this->state(fn () => ['year' => $year]);
    }

    public function withFullBalance(): static
    {
        return $this->state(function () {
            $entitledDays = fake()->randomFloat(2, 15, 30);

            return [
                'entitled_days' => $entitledDays,
                'used_days' => 0,
                'pending_days' => 0,
                'available_balance' => $entitledDays,
            ];
        });
    }

    public function exhausted(): static
    {
        return $this->state(function () {
            $entitledDays = fake()->randomFloat(2, 15, 30);

            return [
                'entitled_days' => $entitledDays,
                'used_days' => $entitledDays,
                'pending_days' => 0,
                'available_balance' => 0,
            ];
        });
    }
}
