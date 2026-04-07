<?php

declare(strict_types=1);

namespace App\DTOs\HR;

use App\DTOs\Contracts\DataTransferObject;

final readonly class PayslipDTO implements DataTransferObject
{
    public function __construct(
        public int     $employeeId,
        public int     $payrollPeriodId,
        public float   $grossEarnings,
        public float   $totalDeductions,
        public float   $netSalary,
        public string  $currencyCode,
        public string  $status,
        public ?int    $journalEntryId = null,
        public ?string $paidAt         = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            employeeId:      (int)   $data['employee_id'],
            payrollPeriodId: (int)   $data['payroll_period_id'],
            grossEarnings:   (float) $data['gross_earnings'],
            totalDeductions: (float) $data['total_deductions'],
            netSalary:       (float) $data['net_salary'],
            currencyCode:    $data['currency_code'] ?? 'SAR',
            status:          $data['status'],
            journalEntryId:  isset($data['journal_entry_id']) ? (int) $data['journal_entry_id'] : null,
            paidAt:          $data['paid_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'employee_id'       => $this->employeeId,
            'payroll_period_id' => $this->payrollPeriodId,
            'gross_earnings'    => $this->grossEarnings,
            'total_deductions'  => $this->totalDeductions,
            'net_salary'        => $this->netSalary,
            'currency_code'     => $this->currencyCode,
            'status'            => $this->status,
            'journal_entry_id'  => $this->journalEntryId,
            'paid_at'           => $this->paidAt,
        ];
    }

    public static function fromModel(\App\Models\HR\Payslip $payslip): static
    {
        return new static(
            employeeId:      $payslip->employee_id,
            payrollPeriodId: $payslip->payroll_period_id,
            grossEarnings:   (float) $payslip->gross_earnings,
            totalDeductions: (float) $payslip->total_deductions,
            netSalary:       (float) $payslip->net_salary,
            currencyCode:    $payslip->currency_code ?? 'SAR',
            status:          $payslip->status,
            journalEntryId:  $payslip->journal_entry_id,
            paidAt:          $payslip->paid_at?->toIso8601String(),
        );
    }
}
