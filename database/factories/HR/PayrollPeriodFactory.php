<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\Core\Organization;
use App\Models\HR\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollPeriodFactory extends Factory
{
    protected $model = PayrollPeriod::class;

    private static int $monthOffset = 0;

    public function definition(): array
    {
        // Use a sequential month offset to avoid unique constraint violations.
        // Call startOfMonth() FIRST to normalise to the 1st before subtracting months,
        // otherwise days 29-31 can overflow (e.g. Mar-29 - 1 month = Feb-29 → Mar-1).
        $offset = self::$monthOffset++;
        $startDate = now()->startOfMonth()->subMonths($offset);
        $endDate = $startDate->copy()->endOfMonth();
        $monthName = $startDate->format('F Y');

        return [
            'organization_id' => Organization::factory(),
            'name' => "Payroll - {$monthName}",
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'payment_date' => $endDate->copy()->addDays(5)->format('Y-m-d'),
            'status' => PayrollPeriod::STATUS_OPEN,
            'processed_by' => null,
            'processed_at' => null,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => PayrollPeriod::STATUS_OPEN]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => PayrollPeriod::STATUS_PROCESSING]);
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => PayrollPeriod::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => PayrollPeriod::STATUS_CLOSED,
            'processed_at' => now()->subDay(),
            'closed_at' => now(),
        ]);
    }
}
