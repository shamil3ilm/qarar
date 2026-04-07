<?php

declare(strict_types=1);

namespace Database\Factories\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\PayrollPeriod;
use App\Models\HR\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        $grossEarnings = fake()->randomFloat(4, 3000, 25000);
        $totalDeductions = round($grossEarnings * fake()->randomFloat(2, 0.10, 0.25), 4);
        $netSalary = round($grossEarnings - $totalDeductions, 4);
        $taxableIncome = round($grossEarnings * 0.85, 4);
        $taxDeducted = round($taxableIncome * fake()->randomFloat(2, 0.05, 0.15), 4);

        return [
            'organization_id' => null,
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'employee_salary_id' => EmployeeSalary::factory(),
            'payslip_number' => strtoupper(fake()->unique()->lexify('PS-####-???')),
            'payment_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'total_working_days' => fake()->randomFloat(2, 20, 26),
            'days_worked' => fake()->randomFloat(2, 18, 26),
            'days_on_leave' => fake()->randomFloat(2, 0, 3),
            'unpaid_leave_days' => fake()->randomFloat(2, 0, 1),
            'overtime_hours' => fake()->randomFloat(2, 0, 20),
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'currency_code' => fake()->randomElement(['SAR', 'AED', 'INR']),
            'taxable_income' => $taxableIncome,
            'tax_deducted' => $taxDeducted,
            'status' => Payslip::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'payment_mode' => fake()->randomElement(['bank_transfer', 'cheque', 'cash']),
            'payment_reference' => null,
            'paid_at' => null,
            'journal_entry_id' => null,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Payslip::STATUS_DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Payslip::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Payslip::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Payslip::STATUS_PAID,
            'approved_at' => now()->subDay(),
            'paid_at' => now(),
            'payment_reference' => strtoupper(fake()->lexify('PAY-########')),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Payslip::STATUS_CANCELLED]);
    }
}
