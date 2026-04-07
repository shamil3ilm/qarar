<?php

declare(strict_types=1);

namespace App\UseCases\HR;

use App\Models\HR\PayrollPeriod;
use App\Services\HR\PayrollService;
use App\UseCases\Contracts\UseCase;

class RunPayrollUseCase implements UseCase
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * Run payroll for a given period.
     *
     * Required key: payroll_period_id
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException
     */
    public function handle(array $data): array
    {
        if (! array_key_exists('payroll_period_id', $data)) {
            throw new \InvalidArgumentException('Missing required key: payroll_period_id');
        }

        $period = PayrollPeriod::findOrFail($data['payroll_period_id']);

        $generated = $this->payrollService->generatePayslips($period, (int) auth()->id());

        return [
            'generated' => $generated,
            'period_id' => $data['payroll_period_id'],
        ];
    }
}
