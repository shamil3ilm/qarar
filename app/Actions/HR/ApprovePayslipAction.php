<?php

declare(strict_types=1);

namespace App\Actions\HR;

use App\Actions\Contracts\Action;
use App\Models\HR\Payslip;
use App\Services\HR\PayrollService;
use InvalidArgumentException;

class ApprovePayslipAction implements Action
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    public function execute(array $payload): Payslip
    {
        if (empty($payload['payslip_id'])) {
            throw new InvalidArgumentException('payslip_id is required.');
        }

        $payslip = Payslip::findOrFail($payload['payslip_id']);

        return $this->payrollService->approvePayslip($payslip, auth()->id());
    }
}
