<?php

declare(strict_types=1);

namespace App\Actions\HR;

use App\Actions\Contracts\Action;
use App\Models\HR\PayrollPeriod;
use App\Services\HR\PayrollService;
use InvalidArgumentException;

class RunPayrollAction implements Action
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * @return array{generated: int, period_id: int, status: string}
     */
    public function execute(array $payload): array
    {
        if (empty($payload['payroll_period_id'])) {
            throw new InvalidArgumentException('payroll_period_id is required.');
        }

        $period = PayrollPeriod::findOrFail($payload['payroll_period_id']);

        $result = $this->payrollService->generatePayslips($period, auth()->id());

        return [
            'generated'  => $result,
            'period_id'  => $period->id,
            'status'     => $period->fresh()->status,
        ];
    }
}
